import fs from 'node:fs';import path from 'node:path';
import {registryDir,cleanupTemporaryRun} from './temporary-uploads.mjs';
const run=process.argv[2];const runs=run?[run]:fs.readdirSync(registryDir).filter(n=>/^[a-f0-9-]{36}\.json$/.test(n)).map(n=>n.slice(0,-5));
if(runs.some(r=>!/^[a-f0-9-]{36}$/.test(r)))throw new Error('Invalid temporary registry UUID');
const receipts=runs.flatMap(cleanupTemporaryRun);
console.log(JSON.stringify({receipts,scope:'Exact signed registry entries only; unregistered historical files preserved'}));
