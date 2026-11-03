"""Scoped measurements: two selected containers and the runner's own PHP workers."""
import json
import math
import re
import shutil
import time
from runtime import COMPOSE,RESERVED,ROOT


def bytes_quantity(value):
    match=re.fullmatch(r'(\d+(?:\.\d+)?)\s*(B|kB|KB|MB|GB|TB|KiB|MiB|GiB|TiB)',value.strip())
    if not match:raise ValueError('resource_quantity_invalid')
    factors={'B':1,'kB':1000,'KB':1000,'MB':1000**2,'GB':1000**3,'TB':1000**4,
             'KiB':1024,'MiB':1024**2,'GiB':1024**3,'TiB':1024**4}
    return round(float(match[1])*factors[match[2]])


def pair(value):
    parts=value.split(' / ')
    if len(parts)!=2:raise ValueError('resource_pair_invalid')
    return [bytes_quantity(part) for part in parts]


class ResourceSampler:
    def __init__(self,runtime,minimum_disk):
        self.runtime=runtime;self.minimum_disk=minimum_disk;self.ids={}

    def prepare(self):
        for service in ('rabbitmq','mysql'):
            result=self.runtime.command('container-'+service,[*COMPOSE,'ps','-q',service])
            identifier=result.stdout.decode('ascii').strip()
            if not re.fullmatch(r'[0-9a-f]{12,64}',identifier):raise RuntimeError('selected_container_unavailable')
            self.ids[service]=identifier

    def sample(self):
        started=time.monotonic_ns()
        workers=[actor for actor in self.runtime.actors if actor.kind=='worker']
        if not workers or not self.runtime.workers_live():raise RuntimeError('resource_worker_not_live')
        pids=[actor.child.pid for actor in workers]
        if len(pids)!=len(set(pids)) or any(pid in RESERVED for pid in pids):raise RuntimeError('resource_process_identity_refused')
        selected=','.join(str(pid) for pid in pids)
        # Exact IDs from owned Popen handles; no global Get-Process query or command lines.
        command="$ErrorActionPreference='Stop'; @(Get-Process -Id "+selected+" | Select-Object Id,CPU,WorkingSet64,PrivateMemorySize64) | ConvertTo-Json -Compress"
        result=self.runtime.command('owned-worker-resources',['powershell','-NoLogo','-NoProfile','-NonInteractive','-Command',command],timeout=15)
        process_rows=json.loads(result.stdout)
        if isinstance(process_rows,dict):process_rows=[process_rows]
        if not isinstance(process_rows,list) or {row.get('Id') for row in process_rows}!=set(pids):raise RuntimeError('resource_process_observation_incomplete')
        by_id={actor.child.pid:actor.label for actor in workers}
        processes={}
        for row in process_rows:
            cpu=row.get('CPU')
            if type(cpu) not in (int,float) or not math.isfinite(cpu) or cpu<0:raise RuntimeError('resource_cpu_unavailable')
            if not all(type(row.get(key)) is int and row[key]>=0 for key in ('WorkingSet64','PrivateMemorySize64')):raise RuntimeError('resource_memory_unavailable')
            processes[by_id[row['Id']]]={'cpu_seconds_cumulative':cpu,'working_set_bytes':row['WorkingSet64'],'private_bytes':row['PrivateMemorySize64']}
        result=self.runtime.command('selected-container-resources',['docker','stats','--no-stream','--no-trunc','--format','{{json .}}',*self.ids.values()],timeout=20)
        containers={}
        for line in result.stdout.splitlines():
            row=json.loads(line)
            identifier=row.get('ID','')
            matches=[service for service,own in self.ids.items() if len(identifier)>=12 and own.startswith(identifier)]
            if len(matches)!=1 or matches[0] in containers:raise RuntimeError('resource_container_identity_mismatch')
            cpu=row.get('CPUPerc','')
            if not re.fullmatch(r'\d+(?:\.\d+)?%',cpu):raise RuntimeError('resource_container_cpu_invalid')
            usage,limit=pair(row['MemUsage']);read,written=pair(row['BlockIO'])
            containers[matches[0]]={'cpu_percent':float(cpu[:-1]),'docker_cli_memory_bytes':usage,'memory_limit_bytes':limit,
                                     'block_read_reported_bytes':read,'block_write_reported_bytes':written}
        if set(containers)!=set(self.ids):raise RuntimeError('resource_container_observation_incomplete')
        for service,mount in [('rabbitmq','/var/lib/rabbitmq'),('mysql','/var/lib/mysql')]:
            result=self.runtime.command('available-disk-'+service,[*COMPOSE,'exec','-T',service,'df','-Pk',mount])
            lines=result.stdout.decode('utf-8').splitlines()
            parts=lines[-1].split() if lines else []
            if len(parts)<6 or not parts[3].isdigit():raise RuntimeError('resource_disk_observation_invalid')
            available=int(parts[3])*1024
            containers[service]['filesystem_available_bytes']=available
            if available<self.minimum_disk:raise RuntimeError('load_disk_stop_threshold_reached')
        if not self.runtime.workers_live():raise RuntimeError('resource_worker_not_live')
        application_free=shutil.disk_usage(ROOT/'storage').free
        if application_free<self.minimum_disk:raise RuntimeError('application_storage_stop_threshold_reached')
        return {'begin_ns':started,'end_ns':time.monotonic_ns(),'owned_workers':processes,'selected_containers':containers,
                'application_storage_available_bytes':application_free,
                'memory_semantics':'Docker CLI reports Linux memory with cache adjustment; native worker values are Windows working sets.',
                'disk_semantics':'Block IO counters retain CLI rounding and are not a proof of zero disk activity; free bytes come from df.',
                'scope':'Worker processes and selected shared service totals; producer/controller overhead is not attributed.'}
