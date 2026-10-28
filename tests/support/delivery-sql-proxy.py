"""Single owned SQL connection, dropped only after an identity-bound test barrier."""
import json
import os
from pathlib import Path
import select
import socket
import sys
import time

root = Path(sys.argv[1])
lifetime = float(sys.argv[4])
if not 0 < lifetime <= 900:
    raise ValueError('Owned SQL proxy lifetime is out of bounds.')
deadline = time.monotonic() + lifetime
listener = socket.socket()
listener.bind(('0.0.0.0', 0))
listener.listen(1)
listener.settimeout(min(.1, lifetime))
tmp = root / 'sql-ready.tmp'
tmp.write_text(json.dumps({'port': listener.getsockname()[1]}))
os.replace(tmp, root / 'sql-ready')
client = server = None
reason = 'budget_exhausted'
try:
    while time.monotonic() < deadline and client is None:
        if (root / 'cut-sql').exists():
            reason = 'requested'
            break
        try:
            client, _ = listener.accept()
        except socket.timeout:
            pass
    if client is None:
        sys.exit(0 if reason == 'requested' else 1)
    server = socket.create_connection((sys.argv[2], int(sys.argv[3])), timeout=min(5, max(.001, deadline - time.monotonic())))
    client.setblocking(False)
    server.setblocking(False)
    while time.monotonic() < deadline:
        if (root / 'cut-sql').exists():
            reason = 'requested'
            break
        for source in select.select([client, server], [], [], .01)[0]:
            data = source.recv(65536)
            if not data:
                raise EOFError()
            (server if source is client else client).sendall(data)
except EOFError:
    reason = 'connection_closed'
except OSError:
    reason = 'socket_error'
finally:
    for endpoint in (client, server, listener):
        if endpoint:
            endpoint.close()
    tmp = root / 'sql-cut.tmp'
    tmp.write_text(json.dumps({'closed': True, 'utc': time.time(), 'reason': reason, 'lifetime_seconds': lifetime}))
    os.replace(tmp, root / 'sql-cut')
sys.exit(0 if reason == 'requested' else 1)
