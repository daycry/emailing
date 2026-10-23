"""Own single-client AMQP fault proxy; never changes the shared broker."""
import json
import os
import pathlib
import select
import socket
import struct
import sys
import time

root = pathlib.Path(sys.argv[1])


def publish_text(path, content):
    temporary = path.with_name(path.name + '.tmp')
    temporary.write_text(content)
    os.replace(temporary, path)


listener = socket.socket()
listener.bind(('127.0.0.1', 0))
listener.listen(1)
listener.settimeout(20)
publish_text(root / 'proxy-ready', str(listener.getsockname()[1]))
client = server = None
try:
    client, _ = listener.accept()
    server = socket.create_connection((sys.argv[2], int(sys.argv[3])), timeout=5)
    client.setblocking(False)
    server.setblocking(False)
    dropped = 0
    buffer = b''
    dropped_methods = []
    deadline = time.monotonic() + 20
    while time.monotonic() < deadline:
        if (root / 'disconnect-sql').exists():
            (root / 'sql-disconnected').touch()
            break
        discard = (root / 'drop-confirm').exists()
        if discard:
            (root / 'drop-armed').touch()
        readable, _, _ = select.select([client, server], [], [], .01)
        for source in readable:
            data = source.recv(65536)
            if not data:
                raise EOFError()
            if source is server and discard:
                dropped += len(data)
                buffer += data
                while len(buffer) >= 7:
                    frame_type, channel, length = struct.unpack('!BHI', buffer[:7])
                    if len(buffer) < length + 8:
                        break
                    payload = buffer[7:7 + length]
                    if frame_type == 1 and len(payload) >= 4:
                        class_id, method_id = struct.unpack('!HH', payload[:4])
                        dropped_methods.append(f'{class_id}:{method_id}')
                    buffer = buffer[length + 8:]
                publish_text(root / 'proxy-observation', json.dumps({'dropped_server_bytes': dropped, 'dropped_methods': dropped_methods}))
            else:
                target = server if source is client else client
                target.sendall(data)
except (EOFError, ConnectionError, OSError):
    pass
finally:
    for endpoint in (client, server, listener):
        if endpoint:
            endpoint.close()
