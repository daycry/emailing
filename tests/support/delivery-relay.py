"""Owned controlled SMTP relay: reserved domains, stage receipts, no message bodies."""
import hashlib
import json
import os
from pathlib import Path
import socketserver
import sys
import threading
import time

root = Path(sys.argv[1])
lock = threading.Lock()

def publish(path, data):
    tmp = path.with_name(path.name + '.tmp')
    tmp.write_text(json.dumps(data), encoding='utf-8')
    os.replace(tmp, path)

def fact(stage, **facts):
    with lock:
        with (root / 'relay.jsonl').open('a', encoding='utf-8') as out:
            out.write(json.dumps(dict(stage=stage, utc=time.time(), **facts)) + '\n')

class Relay(socketserver.StreamRequestHandler):
    def handle(self):
        self.connection.settimeout(30)
        self.wfile.write(b'220 controlled.example.test ESMTP\r\n')
        fact('connected')
        try:
            while True:
                line = self.rfile.readline(4096)
                if not line:
                    return
                mode = (root / 'mode').read_text().strip()
                command = line.split(b' ', 1)[0].strip().upper()
                if command in [b'EHLO', b'HELO']:
                    if mode == 'fallback_lost' and command == b'EHLO':
                        fact('ehlo_rejected')
                        self.wfile.write(b'500 try HELO\r\n')
                    else:
                        self.wfile.write(b'250 controlled.example.test\r\n')
                elif command == b'MAIL':
                    fact('mail')
                    self.wfile.write(b'250 sender ok\r\n')
                elif command == b'RCPT':
                    fact('recipient', rejected=mode == 'reject')
                    if mode == 'cut_before_data':
                        fact('cut_before_data')
                        return
                    self.wfile.write(b'450 controlled refusal\r\n' if mode == 'reject' else b'250 recipient ok\r\n')
                elif command == b'DATA':
                    fact('data')
                    self.wfile.write(b'354 send data\r\n')
                    body = bytearray()
                    while True:
                        part = self.rfile.readline(65536)
                        if part == b'.\r\n':
                            break
                        if not part:
                            return
                        body.extend(part)
                    fact('accepted_data', bytes=len(body), sha256=hashlib.sha256(body).hexdigest(), mode=mode)
                    if mode in ['lost', 'fallback_lost']:
                        fact('final_reply_lost')
                        return
                    if mode == 'hold':
                        deadline = time.monotonic() + 25
                        while not (root / 'release-final').exists() and time.monotonic() < deadline:
                            time.sleep(.01)
                    self.wfile.write(b'250 accepted\r\n')
                    fact('final_250')
                elif command == b'RSET':
                    # Deliberately fail cleanup too: original RCPT rejection must latch.
                    self.wfile.write(b'550 cleanup refusal\r\n' if mode == 'reject' else b'250 reset\r\n')
                elif command == b'QUIT':
                    if mode != 'cleanup_lost':
                        self.wfile.write(b'221 bye\r\n')
                    return
                else:
                    self.wfile.write(b'500 unsupported\r\n')
        except (OSError, ConnectionError):
            fact('connection_closed')

class Server(socketserver.ThreadingTCPServer):
    allow_reuse_address = True
    daemon_threads = True

with Server(('0.0.0.0', 0), Relay) as server:
    server.timeout = .1
    publish(root / 'relay-ready', {'port': server.server_address[1]})
    while not (root / 'relay-stop').exists():
        server.handle_request()
