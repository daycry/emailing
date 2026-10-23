"""Single owned SQL connection, dropped only after an identity-bound test barrier."""
import json
import os
from pathlib import Path
import select
import socket
import sys
import time

root = Path(sys.argv[1])
listener = socket.socket()
listener.bind(('0.0.0.0', 0))
listener.listen(1)
listener.settimeout(40)
tmp = root / 'sql-ready.tmp'
tmp.write_text(json.dumps({'port': listener.getsockname()[1]}))
os.replace(tmp, root / 'sql-ready')
client = server = None
try:
    client, _ = listener.accept()
    server = socket.create_connection((sys.argv[2], int(sys.argv[3])), timeout=5)
    client.setblocking(False)
    server.setblocking(False)
    deadline = time.monotonic() + 60
    while time.monotonic() < deadline:
        if (root / 'cut-sql').exists():
            break
        for source in select.select([client, server], [], [], .01)[0]:
            data = source.recv(65536)
            if not data:
                raise EOFError()
            (server if source is client else client).sendall(data)
except (EOFError, OSError):
    pass
finally:
    for endpoint in (client, server, listener):
        if endpoint:
            endpoint.close()
    (root / 'sql-cut').write_text(json.dumps({'closed': True, 'utc': time.time()}))
