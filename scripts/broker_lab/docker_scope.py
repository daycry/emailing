"""Reconcile only containers attributed to one regression, never a host inventory."""
import json
import re
import uuid

from metrics import require
from runtime import save

LABEL='io.emailing.broker-lab.regression'
NAME=re.compile(r'emailing-(?:t12-crash-|t13-)[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}')


class DockerScope:
    def __init__(self,runtime,run):
        require(type(run) is str and str(uuid.UUID(run))==run and uuid.UUID(run).version==4,'daemon_run_identity_refused')
        self.runtime=runtime
        self.run=run
        self.claimed=False

    def containers(self):
        observed=self.runtime.command('daemon-scoped-list',['docker','container','ls','--all','--no-trunc',
             '--filter','label='+LABEL+'='+self.run,'--format','{{json .}}'],timeout=20)
        rows=[json.loads(line) for line in observed.stdout.splitlines() if line.strip()]
        require(len(rows)<=256,'daemon_scope_limit')
        require(all(type(row) is dict and type(row.get('ID')) is str and re.fullmatch('[0-9a-f]{64}',row['ID'])
                    and type(row.get('Names')) is str and NAME.fullmatch(row['Names']) for row in rows),'daemon_scope_identity_refused')
        require(len({row['ID'] for row in rows})==len(rows),'daemon_scope_duplicate_identity')
        return rows

    def preflight(self):
        require(not self.containers(),'daemon_scope_already_exists')
        save(self.runtime.out/'daemon-attribution.public.json',{'run_id':self.run,'label_key':LABEL,'absence_checked':True})
        self.claimed=True

    def cleanup(self):
        """The native Job must already be closed, so no test can create another container."""
        if not self.claimed:
            return False  # An existing label was never acquired; do not touch it.
        stopped=[]
        verified=False
        failure=None
        try:
            for row in self.containers():
                identifier=row['ID']
                template='{"id":{{json .Id}},"name":{{json .Name}},"run":{{json (index .Config.Labels "'+LABEL+'")}}}'
                observed=self.runtime.command('daemon-owned-inspect',['docker','container','inspect','--format',template,identifier],timeout=15,check=False)
                if observed.returncode!=0:
                    require(identifier not in {value['ID'] for value in self.containers()},'daemon_identity_unverifiable')
                    continue  # It exited and --rm removed it between list and inspect.
                identity=json.loads(observed.stdout)
                require(identity=={'id':identifier,'name':'/'+row['Names'],'run':self.run},'daemon_label_mismatch')
                self.runtime.command('daemon-owned-stop',['docker','container','stop','--time','10',identifier],timeout=25,check=False)
                if identifier in {value['ID'] for value in self.containers()}:
                    self.runtime.command('daemon-owned-remove',['docker','container','rm',identifier],timeout=15,check=False)
                require(identifier not in {value['ID'] for value in self.containers()},'daemon_owned_container_survived')
                stopped.append(identifier)
            verified=not self.containers()
            return verified
        except Exception as error:
            failure=type(error).__name__
            return False
        finally:
            save(self.runtime.out/'daemon-cleanup.public.json',{'run_id':self.run,'label_key':LABEL,
                 'absence_checked_before_suite':True,'owned_stopped_ids':stopped,'daemon_cleanup_verified':verified,'failure_class':failure})
