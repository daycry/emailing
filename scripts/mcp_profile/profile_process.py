"""Bound an owned gated probe and its descendants; never enumerate host processes."""
from dataclasses import dataclass, field
import math
import os
import signal
import subprocess
import threading
import time

from profile_receipts import EvidenceError

GATE = b'profile-execute-v1\n'


class _WindowsJob:
    def __init__(self):
        import ctypes as c
        from ctypes import wintypes as w

        class Basic(c.Structure):
            _fields_ = [('process_time', c.c_int64), ('job_time', c.c_int64),
                        ('flags', w.DWORD), ('minimum', c.c_size_t), ('maximum', c.c_size_t),
                        ('active', w.DWORD), ('affinity', c.c_size_t),
                        ('priority', w.DWORD), ('scheduling', w.DWORD)]

        class Io(c.Structure):
            _fields_ = [(name, c.c_uint64) for name in ['read_ops', 'write_ops', 'other_ops',
                                                      'read_bytes', 'write_bytes', 'other_bytes']]

        class Extended(c.Structure):
            _fields_ = [('basic', Basic), ('io', Io), ('process_memory', c.c_size_t),
                        ('job_memory', c.c_size_t), ('peak_process', c.c_size_t),
                        ('peak_job', c.c_size_t)]

        self._api = c.WinDLL('kernel32', use_last_error=True)
        signatures = {
            'CreateJobObjectW': ([c.c_void_p, w.LPCWSTR], w.HANDLE),
            'SetInformationJobObject': ([w.HANDLE, c.c_int, c.c_void_p, w.DWORD], w.BOOL),
            'AssignProcessToJobObject': ([w.HANDLE, w.HANDLE], w.BOOL),
            'OpenProcess': ([w.DWORD, w.BOOL, w.DWORD], w.HANDLE),
            'CloseHandle': ([w.HANDLE], w.BOOL),
        }
        for name, (args, result) in signatures.items():
            fn = getattr(self._api, name)
            fn.argtypes, fn.restype = args, result
        self._handle = self._api.CreateJobObjectW(None, None)
        if not self._handle:
            raise EvidenceError('process_containment_unavailable')
        limit = Extended()
        limit.basic.flags = 0x2000  # JOB_OBJECT_LIMIT_KILL_ON_JOB_CLOSE; no breakaway.
        if not self._api.SetInformationJobObject(self._handle, 9, c.byref(limit), c.sizeof(limit)):
            self.close()
            raise EvidenceError('process_containment_unavailable')

    def attach(self, child):
        handle = self._api.OpenProcess(0x0101, False, child.pid)  # SET_QUOTA | TERMINATE.
        if not handle:
            raise EvidenceError('process_containment_unavailable')
        try:
            if not self._api.AssignProcessToJobObject(self._handle, handle):
                raise EvidenceError('process_containment_unavailable')
        finally:
            self._api.CloseHandle(handle)

    def close(self):
        if self._handle:
            handle, self._handle = self._handle, None
            if not self._api.CloseHandle(handle):
                raise EvidenceError('process_cleanup_failed')


@dataclass(frozen=True, slots=True)
class ProcessResult:
    exit_code: int
    stdout: bytes = field(repr=False)
    elapsed_ns: int


def run_bounded(command, *, cwd, timeout=180, max_output_bytes=1048576):
    """Command MUST await GATE on stdin before importing/running application work.

    Windows children inherit an unnamed, non-inheritable kill-on-close Job Object.
    POSIX children inherit a new process group. Output is capped while reading.
    No target output or arbitrary OS exception is placed in an error message.
    """
    if (type(command) not in (list, tuple) or not command
            or any(type(part) is not str or not part or '\0' in part for part in command)
            or type(timeout) not in (int, float) or not math.isfinite(timeout) or not 0 < timeout <= 3600
            or type(max_output_bytes) is not int or not 0 < max_output_bytes <= 8 * 1048576):
        raise EvidenceError('invalid_process_config')
    started = time.monotonic_ns()
    child = job = reader = None
    output = bytearray()
    exceeded, read_failed = threading.Event(), threading.Event()
    error = None
    try:
        if os.name == 'nt':
            job = _WindowsJob()
        child = subprocess.Popen(command, cwd=cwd, stdin=subprocess.PIPE, stdout=subprocess.PIPE,
                                 stderr=subprocess.DEVNULL, close_fds=True,
                                 creationflags=subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0,
                                 start_new_session=os.name != 'nt')
        if job:
            job.attach(child)

        def collect():
            try:
                while True:
                    block = child.stdout.read1(min(65536, max_output_bytes + 1))
                    if not block:
                        return
                    if len(output) + len(block) > max_output_bytes:
                        exceeded.set()
                        return
                    output.extend(block)
            except Exception:
                read_failed.set()

        reader = threading.Thread(target=collect, daemon=True)
        reader.start()
        child.stdin.write(GATE)
        child.stdin.close()
        deadline = started + int(timeout * 1_000_000_000)
        while child.poll() is None:
            if exceeded.is_set():
                raise EvidenceError('process_output_limit')
            if read_failed.is_set():
                raise EvidenceError('process_output_unavailable')
            if time.monotonic_ns() >= deadline:
                raise EvidenceError('process_deadline_exceeded')
            time.sleep(0.01)
    except EvidenceError as exc:
        error = str(exc)
    except Exception:
        error = 'process_execution_failed'
    finally:
        # Close containment even after normal child exit: descendants may retain stdout.
        if job:
            try:
                job.close()
            except Exception:
                error = 'process_cleanup_failed'
        elif child and os.name != 'nt':
            try:
                os.killpg(child.pid, signal.SIGKILL)
            except ProcessLookupError:
                pass
            except Exception:
                error = 'process_cleanup_failed'
        if child:
            try:
                if child.poll() is None:
                    child.kill()  # Only the directly created child; gate stayed closed on attach error.
                child.wait(timeout=5)
                if reader:
                    reader.join(timeout=5)
                    if reader.is_alive():
                        error = 'process_cleanup_failed'
                for stream in [child.stdin, child.stdout]:
                    if stream and not stream.closed:
                        stream.close()
            except Exception:
                error = 'process_cleanup_failed'
    if exceeded.is_set() and error is None:
        error = 'process_output_limit'
    if read_failed.is_set() and error is None:
        error = 'process_output_unavailable'
    if error is not None:
        raise EvidenceError(error)
    return ProcessResult(child.returncode, bytes(output), time.monotonic_ns() - started)
