"""Owned subprocesses and RabbitMQ topology for isolated laboratory workloads."""
from dataclasses import dataclass
from pathlib import Path
import json
import os
import re
import subprocess
import sys
import threading
import time
import uuid

BASE=Path(__file__).resolve().parent
ROOT=BASE.parents[1]
DATA_ROOT=ROOT/'storage/broker-lab-evidence'
sys.path.insert(0,str(ROOT/'scripts/mcp_profile'))
from profile_process import GATE,run_bounded
FLAGS=subprocess.CREATE_NO_WINDOW if os.name=='nt' else 0
def protected_process_ids(value):
    if value=='':return frozenset()
    parts=value.split(',')
    if len(parts)>64 or any(not re.fullmatch(r'[1-9][0-9]{0,9}',part) or int(part)>4294967295 for part in parts):
        raise RuntimeError('invalid_protected_process_ids')
    return frozenset(int(part) for part in parts)

RESERVED=protected_process_ids(os.environ.get('BROKER_LAB_PROTECTED_PIDS',''))
COMPOSE=['docker','compose','--env-file','infra/.env','-f','infra/compose.yaml']


def save(path,value):
    """Atomic receipt replacement, with a durable file before remote actions."""
    if not path.parent.resolve().is_relative_to(ROOT/'storage'):raise RuntimeError('receipt_directory_refused')
    temporary=path.with_name(path.name+'.tmp-'+uuid.uuid4().hex)
    data=(json.dumps(value,indent=2)+'\n').encode('utf-8')
    try:
        with temporary.open('xb') as handle:
            if handle.write(data)!=len(data):raise RuntimeError('receipt_write_failed')
            handle.flush();os.fsync(handle.fileno())
        os.replace(temporary,path)
    finally:
        if temporary.is_file():temporary.unlink()  # Exact newly created sibling, verified inside storage.


def records(path, *, terminal=True):
    if not path.is_file():return []
    if path.is_symlink() or path.stat().st_size>8*1048576:raise RuntimeError('owned_output_refused')
    raw=path.read_bytes()
    if terminal and raw and not raw.endswith(b'\n'):raise RuntimeError('incomplete_terminal_stream')
    lines=raw.split(b'\n')[:-1]
    return [json.loads(line) for line in lines if line.strip()]


@dataclass
class Actor:
    label: str
    kind: str
    child: subprocess.Popen
    stdout: object
    stderr: object
    output_path: Path
    error_path: Path
    started_ns: int

    def observations(self):
        return records(self.output_path,terminal=self.child.poll() is not None)


class Runtime:
    def __init__(self,run,out,prefix='worker'):
        try:valid_run=str(uuid.UUID(run))==run and uuid.UUID(run).version==4
        except (ValueError,TypeError,AttributeError):valid_run=False
        allowed={'t19-load-'+run,'t19-resource-probe-'+run,'t19-controller-unit-'+run,'t19-regression-'+run} if valid_run else set()
        if (not valid_run or not re.fullmatch(r'[a-z][a-z-]{0,30}',prefix)
                or not out.is_dir() or out.is_symlink() or out.name not in allowed
                or not out.resolve().is_relative_to(ROOT/'storage')):
            raise RuntimeError('owned_output_directory_refused')
        self.run=run;self.out=out;self.prefix=prefix;self.sequence=0;self.actors=[]
        self._command_lock=threading.Lock()
        ini=out/'php-ini';ini.mkdir(exist_ok=True)
        if ini.is_symlink() or not ini.resolve().is_relative_to(out.resolve()):raise RuntimeError('owned_php_ini_refused')
        settings={'sockets.ini':b'extension=sockets\nzend.exception_ignore_args=1\n',
                  'uploads.ini':(ROOT/'docker/emailing-uploads.ini').read_bytes()}
        for name,content in settings.items():
            path=ini/name
            if path.is_symlink() or (path.exists() and path.stat().st_nlink!=1):raise RuntimeError('owned_php_ini_refused')
            path.write_bytes(content)
        self.env=os.environ.copy()
        self.env.update(APP_ENV='testing',DB_DATABASE='emailing_testing',DB_USERNAME='emailing_testing',DB_URL='',
                        PHP_INI_SCAN_DIR=str(ini),PYTHONDONTWRITEBYTECODE='1')

    def command(self,label,argv,*,input_data=None,timeout=30,check=True):
        if not re.fullmatch(r'[a-z0-9-]{1,80}',label):raise RuntimeError('invalid_command_label')
        with self._command_lock:
            self.sequence+=1
            stem=f'{self.prefix}-{self.sequence:04d}-{label}'
        started=time.monotonic_ns()
        try:
            result=subprocess.run(argv,cwd=ROOT,env=self.env,input=input_data,capture_output=True,timeout=timeout,creationflags=FLAGS)
        except subprocess.TimeoutExpired as error:
            (self.out/(stem+'.stdout.private.txt')).write_bytes(error.stdout or b'')
            (self.out/(stem+'.stderr.private.txt')).write_bytes(error.stderr or b'')
            save(self.out/(stem+'.receipt.public.json'),{'label':label,'exit':None,'terminal_reason':'command_deadline',
                 'started_ns':started,'ended_ns':time.monotonic_ns()})
            raise RuntimeError('owned_command_deadline') from None
        if len(result.stdout)+len(result.stderr)>8*1048576:raise RuntimeError('owned_output_limit')
        (self.out/(stem+'.stdout.private.txt')).write_bytes(result.stdout)
        (self.out/(stem+'.stderr.private.txt')).write_bytes(result.stderr)
        save(self.out/(stem+'.receipt.public.json'),{'label':label,'exit':result.returncode,'started_ns':started,
             'ended_ns':time.monotonic_ns(),'stdout_bytes':len(result.stdout),'stderr_bytes':len(result.stderr)})
        if check and result.returncode!=0:raise RuntimeError('owned_command_failed')
        return result

    def cli(self,label,*args):
        return self.command(label,[*COMPOSE,'exec','-T','rabbitmq','rabbitmqctl','--timeout','15',*args],timeout=25)

    def namespace_exists(self,app,kind):
        name='lab.t19.'+self.run+'.'+app
        args=['list_vhosts','name'] if kind=='vhost' else ['list_users']
        result=self.cli('probe-'+app+'-'+kind,'-q','--formatter=json',*args)
        rows=json.loads(result.stdout)
        key='name' if kind=='vhost' else 'user'
        if not isinstance(rows,list) or not all(isinstance(row,dict) and isinstance(row.get(key),str) for row in rows):
            raise RuntimeError('namespace_observation_invalid')
        return any(row[key]==name for row in rows)

    def queues(self,app,*,allow_absent=False):
        name='lab.t19.'+self.run+'.'+app
        result=self.cli('queues-'+app,'-q','--formatter=json','list_queues','-p',name,
                        'name','messages_ready','messages_unacknowledged','consumers')
        rows=json.loads(result.stdout)
        if rows==[] and allow_absent:return None
        if not isinstance(rows,list) or len(rows)!=1 or rows[0].get('name')!=name+'.ready':
            raise RuntimeError('owned_queue_observation_invalid')
        row=rows[0]
        if not all(type(row.get(key)) is int and row[key]>=0 for key in ('messages_ready','messages_unacknowledged','consumers')):
            raise RuntimeError('owned_queue_count_invalid')
        return {key:row[key] for key in ('messages_ready','messages_unacknowledged','consumers')}

    def spawn(self,label,kind,script,descriptor):
        if kind not in ('worker','producer') or script not in ('emailing_action.php','protocol_worker.php','protocol_publisher.php'):
            raise RuntimeError('actor_program_refused')
        if not re.fullmatch(r'[a-z0-9-]{1,40}',label) or any(actor.label==label for actor in self.actors):
            raise RuntimeError('actor_identity_invalid')
        raw=json.dumps(descriptor).encode()
        if len(raw)>16384:raise RuntimeError('actor_descriptor_limit')
        path=self.out/(label+'.stdout.private.txt');error=self.out/(label+'.stderr.private.txt')
        stdout=path.open('xb');stderr=error.open('xb')
        child=None
        try:
            child=subprocess.Popen(['php','-d','zend.exception_ignore_args=1',str(BASE/script)],cwd=ROOT,env=self.env,
                                   stdin=subprocess.PIPE,stdout=stdout,stderr=stderr,creationflags=FLAGS)
            if child.pid in RESERVED:
                child.stdin.close()
                stdout.close();stderr.close()
                raise RuntimeError('reserved_process_identity_refused')
            actor=Actor(label,kind,child,stdout,stderr,path,error,time.monotonic_ns())
            self.actors.append(actor)
            try:child.stdin.write(raw)
            finally:child.stdin.close()
            return actor
        except BaseException:
            if child is None:stdout.close();stderr.close()
            raise

    def php(self,label,descriptor,*,timeout=30):
        raw=json.dumps(descriptor).encode()
        if len(raw)>16384:raise RuntimeError('actor_descriptor_limit')
        result=self.command(label,['php','-d','zend.exception_ignore_args=1',str(BASE/'emailing_action.php')],input_data=raw,timeout=timeout)
        observations=[json.loads(line) for line in result.stdout.splitlines() if line.strip()]
        if len(observations)!=1 or observations[0].get('outcome')=='failed':raise RuntimeError('owned_php_action_failed')
        return observations[0]

    def workers_live(self):
        workers=[actor for actor in self.actors if actor.kind=='worker']
        return bool(workers) and all(actor.child.poll() is None for actor in workers)

    def stop(self,directories):
        for directory in directories:(directory/'stop').write_bytes(b'stop\n')
        forced=False
        for actor in self.actors:
            actor_forced=False
            try:
                actor.child.wait(timeout=20)
            except subprocess.TimeoutExpired:
                if actor.child.pid in RESERVED:raise RuntimeError('reserved_process_identity_refused')
                actor.child.terminate();forced=True;actor_forced=True
                actor.child.wait(timeout=10)
            finally:
                actor.stdout.close();actor.stderr.close()
            save(self.out/(actor.label+'.terminal.public.json'),{'label':actor.label,'kind':actor.kind,
                 'exit':actor.child.returncode,'terminal':True,'forced_owned_stop':actor_forced,
                 'started_ns':actor.started_ns,'ended_ns':time.monotonic_ns()})
        return not forced and all(actor.child.returncode==0 for actor in self.actors)
