all:

get-data:
	scp apply.neffa.org:aux/backups/latest.gz /tmp/
	gunzip < /tmp/latest.gz | mysql apply-pace
	scp apply.neffa.org:aux/webgrid.tsv /var/apply-pace/
