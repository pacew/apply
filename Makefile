all:

get-data:
	scp -C 'apply.neffa.org:aux/{backups/latest.gz,webgrid.tsv,neffa_idx.json}' /var/apply-pace/.
	gunzip < /var/apply-pace/latest.gz | mysql apply-pace

get-live-data:
	ssh apply.neffa.org 'bash -c "cd apply.neffa.org && ./backup"'
	scp apply.neffa.org:apply.neffa.org/current.sql .
	mysql apply-pace < current.sql
