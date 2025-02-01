all:

get-data:
	scp -C 'apply.neffa.org:aux/{backups/latest.gz,webgrid.tsv,neffa_idx.json}' /var/apply-pace/.
	gunzip < /var/apply-pace/latest.gz | mysql apply-pace
