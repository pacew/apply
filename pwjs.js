/* -*- mode: javascript -*- */

/* only built in packages can go here */
const fs = require ('fs');
const os = require ('os');
const path = require ('path');
const child_process = require ('child_process');

/* extra packages we'll have available below */
const pkgs = [ "sprintf-js", "express", "https", "he", "express-session", 
	       "connect-pg-simple", "ws", "knex" ];


if (! fs.existsSync ("package.json")) {
  console.log ("npm --yes init");
  process.exit (1);
}

const package = JSON.parse (fs.readFileSync ("package.json"));

let need_config = [];
let need_install = false;
pkgs.forEach ((pkg) => {
  let config_flag = false;
  let install_flag = false;
  if (package.dependencies && package.dependencies[pkg])
    config_flag = true;
  if (package.devDependencies && package.devDependencies[pkg])
    config_flag = true;
  if (fs.existsSync ("node_modules/" + pkg))
    install_flag = true;
  if (! config_flag)
    need_config.push (pkg);
  if (! install_flag)
    need_install = true;
});  

if (need_config.length > 0) {
  console.log ("npm install --save " + need_config.join (" "));
  process.exit (1);
} else if (need_install) {
  console.log ("npm install");
  process.exit (1);
}

let p = {};

/* in production, this should come from the git commit number */
p.cache_defeater = Math.floor (Math.random() * 1e9);
exports.cache_defeater = p.cache_defeater;

var sprintf_js = require ('sprintf-js');
var sprintf = sprintf_js.sprintf;
var vsprintf = sprintf_js.vsprintf;
function printf (fmt, ...args) { process.stdout.write (vsprintf (fmt, args)); }

global.printf = printf;
global.sprintf = sprintf;

const express = require ('express');
const https = require ('https');
const he = require ('he');
const session = require ('express-session');
const pgSession = require ('connect-pg-simple')(session);
const ws = require('ws');

const { Pool } = require ('pg');

let app, server, wss;

function system (cmd) {
  return (child_process.execSync (cmd, { encoding: "utf8" })
	  .trim());
}

function slurp_file (filename) {
    try {
      return (fs.readFileSync (filename, "utf8"));
    } catch (e) {
	if (e.code != 'ENOENT') {
	    console.log (e);
	    process.exit (1);
	}
    }

    return ("");
}

function read_json_file (filename) {
    var str = slurp_file (filename);
    if (str == "") {
	return ({});
    }
    
    try {
	return (JSON.parse (str));
    } catch (e) {
	console.log ("error parsing " + filename);
	console.log (e);
	process.exit (1);
    }
}

function get_free_port (port_base) {
    return (port_base + Math.floor (Math.random () * 1000));
}

/* 
 * cfg.json has values specific to a particular developer, but no secrets
 * it is not checked into git
 *
 * options.json is for the whole site (not developer)
 * it is checked into git
 */

var site_cfg;
function get_cfg () {
  if (site_cfg == null) {
    site_cfg = read_json_file ("cfg.json");
    site_cfg.options = read_json_file ("options.json");
  }
  return (site_cfg);
}
exports.get_cfg = get_cfg;

async function make_virtual_host (cfg, ssl_flag, port) {
  let conf = "";
  if (port != 80 && port != 443) {
    conf += sprintf ("Listen %d\n", port);
  }
  conf += sprintf ("<VirtualHost *:%d>\n", port);
  conf += sprintf ("  ServerName %s\n", cfg.external_name);
  conf += sprintf ("  ServerAlias www.%s\n", cfg.external_name);

  if (ssl_flag) {
    conf += sprintf ("  SSLEngine on\n");
    conf += sprintf ("  SSLCertificateFile %s\n", cfg.crt_file);
    conf += sprintf ("  SSLCertificateKeyFile %s\n", cfg.key_file);
    if (cfg.chain_file)
      conf += sprintf ("  SSLCertificateChainFile %s\n", cfg.chain_file);
  }

  conf += sprintf ("  php_flag display_errors on\n");
  conf += sprintf ("  DocumentRoot %s\n", cfg.www_dir);
  conf += sprintf ("  SetEnv APP_ROOT %s\n", cfg.srcdir);
  conf += sprintf ("  <Directory %s>\n", cfg.www_dir);
  conf += sprintf("     <IfModule valhtml_module>\n");
  conf += sprintf ("      AddOutputFilterByType VALHTML text/html\n");
  conf += sprintf ("      SetEnv no-gzip 1\n");
  conf += sprintf ("    </IfModule>\n");
  conf += sprintf ("    <FilesMatch '\.(html|css|js)'>\n");
  conf += sprintf ("      Header set Cache-Control 'no-cache,"+
		   " no-store, must-revalidate'\n");
  conf += sprintf ("      Header set Pragma 'no-cache'\n");
  conf += sprintf ("      Header set Expires 0\n");
  conf += sprintf ("    </FilesMatch>\n");
  conf += sprintf ("  </Directory>\n");
  conf += sprintf ("  DirectoryIndex index.php\n");

  conf += sprintf ("  RewriteEngine on\n");
  conf += sprintf ("  RewriteCond %%{REQUEST_URI} /.well-known/.*\n");
  conf += sprintf ("  RewriteRule ^(.*) /var/www/html/$1 [L]\n");
  conf += "\n";

  if (ssl_flag == 0) {
    if (cfg.ssl_url) {
      conf += sprintf ("  RewriteRule ^/(.*) %s$1 [R]\n", cfg.ssl_url);
    } else {
      conf += sprintf ("  RewriteCond %%{HTTP_HOST} www.%s\n", 
		       cfg.external_name);
      conf += sprintf ("  RewriteRule ^/(.*) %s$1 [R]\n", cfg.plain_url);
    }
  } else {
    conf += sprintf ("  RewriteCond %%{HTTP_HOST} www.%s\n", cfg.external_name);
    conf += sprintf ("  RewriteRule ^/(.*) %s$1 [R]\n", cfg.ssl_url);
  }
  conf += "\n";


  conf += sprintf ("</VirtualHost>\n");
  conf += "\n";

  return (conf);
}

async function setup_apache (cfg) {
  let conf = "";

  cfg.www_dir = sprintf ("/var/www/%s", cfg.siteid);
  if (! fs.existsSync (cfg.www_dir)) {
    printf ("sudo ln -s %s/public %s\n", cfg.srcdir, cfg.www_dir);
  }

  if (! fs.existsSync (cfg.auxdir)) {
    printf ("sudo sh -c 'mkdir -p -m 2775 %s; chown www-data.www-data %s'\n",
	    cfg.auxdir, cfg.auxdir);
  }

  if (cfg.plain_port)
    conf += await make_virtual_host (cfg, 0, cfg.plain_port);

  if (cfg.ssl_port)
    conf += await make_virtual_host (cfg, 1, cfg.ssl_port);

  fs.writeFileSync ("TMP.conf", conf);

  av_name = sprintf ("/etc/apache2/sites-available/%s.conf", cfg.siteid);
  en_name = sprintf ("/etc/apache2/sites-enabled/%s.conf", cfg.siteid);

  let old = slurp_file (av_name);
  if (old != conf) {
    printf ("sudo sh -c 'cp TMP.conf %s; apache2ctl graceful'\n", av_name);
  }

  if (slurp_file (en_name) == "") {
    printf ("sudo a2ensite %s\n", cfg.siteid);
  }

}

async function find_certs (cfg) {
  delete cfg.crt_file;
  delete cfg.key_file;
  delete cfg.chain_file;

  if (cfg.external_name == "localhost") {
    cfg.crt_file = "/etc/apache2/localhost.crt";
    cfg.key_file ="/etc/apache2/localhost.key";
    return;
  }

  if (cfg.external_name.match (/pacew.org$/)
      && fs.existsSync ("/etc/apache2/wildcard.pacew.org.crt")) {
    cfg.crt_file = "/etc/apache2/wildcard.pacew.org.crt";
    cfg.key_file = "/etc/apache2/wildcard.pacew.org.key";
    cfg.chain_file = "/etc/apache2/wildcard.pacew.org.chain.pem";
    return;
  }

  const live = "/etc/letsencrypt/live";
  let crt_file = sprintf ("%s/%s/fullchain.pem", live, cfg.external_name);
  if (fs.existsSync (crt_file)) {
    cfg.crt_file = crt_file;
    cfg.key_file = sprintf ("%s/%s/privkey.pem", live, cfg.external_name);
    return;
  }

  if (! fs.existsSync ("/var/www/html/.well-known")) {
    printf ("sudo sh -c '" +
	    " mkdir -m 2775 /var/www/html/.well-known;" +
	    " chown www-data.www-data /var/www/html/.well-known" +
	    "'\n");
  }

  let gid = parseInt(system("getent group ssl-cert | awk -F: '{print $3}'"));

  let dirs = [ 
    "/var/log/letsencrypt", 
    "/etc/letsencrypt", 
    "/var/lib/letsencrypt" 
  ];
  let die = 0;
  for (let dir of dirs) {
    if (! fs.existsSync (dir)) {
      printf ("sudo mkdir -m 2770 %s\n", dir);
      printf ("sudo chgrp ssl-cert %s\n", dir);
      die = 1;
    } else if (fs.statSync(dir).gid != gid) {
      printf ("sudo find %s -type d " +
	      " -exec chgrp ssl-cert {} \\;" +
	      " -exec chmod 2770 {} \\;\n",
	      dir);
    }
  }

  if (! fs.existsSync ("/etc/letsencrypt/accounts")) {
    printf ("you need something like this on a devel system:\n");
    printf ("rsync -a /etc/letsencrypt/accounts" +
	    " %s:/etc/letsencrypt\n",
	    cfg.external_name);
  }

  let cmd = sprintf ("certbot" +
		     " certonly" +
		     " --webroot" +
		     " --webroot-path /var/www/html" +
		     " --domain %s",
		     cfg.external_name);
  if (! cfg.external_name.match (new RegExp ("[.].*[.]"))) {
    /* only one dot in name, so add www */
    cmd += sprintf (" --domain www.%s", cfg.external_name);
  }
  printf ("you'll need this, but get the non-ssl site up first:\n");
  printf ("%s\n", cmd);
}

function make_url (scheme, host, port) {
  let url = sprintf ("%s://%s", scheme, host);
  if (port != 80 && port != 443)
    url += sprintf (":%d", port);
  url += "/";
  return (url);
}

async function install_site () {
  const cfg = get_cfg ();

  if (! cfg.options.site_type) {
    printf ("cfg.options.site_type not defined\n");
    process.exit (1);
  }

  let nat_name;
  let port_base;
  const nat_info = slurp_file ("/etc/apache2/NAT_INFO");
  if (nat_info) {
    const arr = nat_info.split (" ");
    nat_name = arr[0];
    port_base = parseInt (arr[1]);
  } else {
    nat_name = os.hostname();
    port_base = 8000;
  }

  cfg.srcdir = process.cwd ();
  let basename = path.basename (cfg.srcdir);
  let matches = /(.*)-([^-]*)$/.exec (basename);
  if (matches) {
    cfg.site_name = matches[1];
    cfg.conf_key = matches[2];
  } else {
    cfg.site_name = basename;
    cfg.conf_key = path.basename (os.homedir ());
  }    
  cfg.siteid = sprintf ("%s-%s", cfg.site_name, cfg.conf_key);
  cfg.auxdir = sprintf ("/var/%s", cfg.siteid);
  
  server_name = os.hostname();
  if (cfg.options[server_name] && cfg.options[server_name][cfg.siteid]) {
    let sinfo = cfg.options[server_name][cfg.siteid];
    cfg.external_name = sinfo.external_name;
    cfg.dbinst = sinfo.dbinst;
    cfg.plain_port = sinfo.plain_port ? sinfo.plain_port : 80;
    cfg.ssl_port = sinfo.ssl_port ? sinfo.ssl_port : 443;
  } else {
    cfg.external_name = nat_name;
    cfg.dbinst = "local";
  }

  if (! cfg.plain_port)
    cfg.plain_port = get_free_port (port_base);

  cfg.plain_url = make_url ("http", cfg.external_name, cfg.plain_port);
  cfg.main_url = cfg.plain_url;

  await find_certs (cfg);

  if (cfg.crt_file) {
    if (! cfg.ssl_port)
      cfg.ssl_port = get_free_port (port_base);

    cfg.ssl_url = make_url ("https", cfg.external_name, cfg.ssl_port);
    cfg.main_url = cfg.ssl_url;
  } else {
    cfg.ssl_port = 0;
    cfg.ssl_url = null;
  }

  if (cfg.options.node_packages) {
    let need = [];
    npm_package = read_json_file ("package.json");
    for (var idx in cfg.options.node_packages) {
      var pkg = cfg.options.node_packages[idx];
      if (! npm_package.dependencies[pkg]) {
	need.push (pkg);
      }
    }
    if (need.length) {
      printf ("npm install --save %s\n", need.join(" "));
    }
  }
  
  if (cfg.options.db == "postgres") {
    await setup_postgres (cfg);
  }    

  if (cfg.options.site_type == "php") {
    await setup_apache (cfg);
  }

  if (! cfg.session_secret)
    cfg.session_secret = 's' + Math.floor (Math.random () * 1e9);

  
  if (cfg.ssl_url)
    printf ("%s\n", cfg.ssl_url);
  if (cfg.plain_url)
    printf ("%s\n", cfg.plain_url);

  if (cfg.options.example_path) {
    printf ("%s%s\n", cfg.main_url, cfg.options.example_path);
  }

  const tmpname = "TMP.cfg";
  let cfg1 = Object.assign ({}, cfg);
  delete cfg1.options;
  fs.writeFileSync (tmpname, JSON.stringify (cfg1 , null, "\t") + "\n");
  fs.renameSync (tmpname, "cfg.json");

  await postgres_finish ();

  return (cfg);
}
exports.install_site = install_site;

async function fatal (msg) {
  console.log ("fatal error", msg);
  await postgres_finish ();
  process.exit (1);
}

async function postgres_finish () {
  await do_commit ();
  if (db_pool) {
    db_pool.end ();
    db_pool = null;
  }
}
exports.postgres_finish = postgres_finish;

let db_pool = null;
let db_client = null;
let in_transaction = false;

function get_db_pool () {
  if (db_pool == null) {
    let cfg = get_cfg ();
    db_pool = new Pool ({
      host: '/var/run/postgresql',
      database: cfg.siteid
    });
  }
  return (db_pool);
}

async function query_raw (stmt, params = []) {
  let pool = get_db_pool ();

  if (db_client == null) {
    db_client = await db_pool.connect ();
  }

  if (! in_transaction) {
    await db_client.query ("begin");
    in_transaction = true;
  }

  return (db_client.query (stmt, params));
}

async function query (stmt, params = []) {
  let res = await p.query_raw (stmt, params);
  return (res.rows);
}

async function do_commit () {
  if (db_client) {
    if (in_transaction) {
      await db_client.query ("commit");
      in_transaction = false;
    }
    db_client.release ();
    db_client = null;
  }
}

function get_pg_conf (cfg) {
  let conn = {};

  if (cfg.dbinst == "local") {
    conn.host ="/var/run/postgresql";
  } else {
    let secrets = JSON.parse (slurp_file ("/var/lightsail-conf/secrets.json"));
    let val = secrets[cfg.dbinst];
    if (val == null)
      fatal ("can't find dbinst " + cfg.dbinst);

    conn.host = val.host;
    if (val.password)
      conn.password = val.password;
    if (val.user)
      conn.user = val.user;
  }
  
  let conf = {};
  conf.client = "pg";
  conf.connection = conn;
  
  return (conf);
}

async function setup_postgres (cfg) {
  let conf = get_pg_conf (cfg);

  if (conf.connection.password) {
    let new_row = sprintf ("%s:*:*:%s:%s",
			   conf.connection.host,
			   conf.connection.user,
			   conf.connection.password);
    let pgpass = sprintf ("%s/.pgpass", process.env['HOME']);
    let new_rows = [];
    let used = false;

    if (fs.existsSync (pgpass)) {
      let old_rows = slurp_file(pgpass).split("\n");
      let key = new_row.replace (/[^:]*$/, "");
      for (let old_row of old_rows) {
	if (old_row.substring (0, key.length) == key) {
	  used = true;
	  new_rows.push (new_row);
	} else if (old_row.trim() != "") {
	  new_rows.push (old_row);
	}
      }
    }
    if (! used)
      new_rows.push (new_row);
    fs.writeFileSync (pgpass, new_rows.join("\n") + "\n");
    fs.chmodSync (pgpass, 0600);
  }
  delete (conf.connection.password);

  conf.connection.database = cfg.siteid;
    
  fs.writeFileSync ("knexfile.js",
		    "module.exports = " +
		    JSON.stringify (conf , null, "\t") +
		    "\n");
   
  conf.connection.database = "template1";
  let pool = new Pool (conf.connection);
  
  let res = await pool.query ("select 0"
			      +" from pg_database"
			      +" where datname = $1",
			      [cfg.siteid]);
  if (res.rows.length == 0) {
    printf ("creating database %s\n", cfg.siteid);
    await pool.query (sprintf ("create database \"%s\"", cfg.siteid));
  }

  pool.end();

  if (! fs.existsSync ("migrations")) {
    printf ("knex migrate:make start\n");
  }

  let evars = "";
  if (cfg.dbinst != "local") {
    evars += sprintf ("PGHOST='%s' PGUSER='%s' ",
		      conf.connection.host, 
		      conf.connection.user);
  }
  evars += sprintf ("PGDATABASE='%s'", cfg.siteid);

  let txt;

  txt = "#! /bin/sh\n";
  txt += sprintf ("%s exec psql \"$@\"\n", evars, cfg.siteid);
  fs.writeFileSync ("sql", txt);
  fs.chmodSync ("sql", 0775);

  txt = "#! /bin/sh\n";
  txt += sprintf ("%s exec pg_dump \"$@\"\n", evars, cfg.siteid);
  fs.writeFileSync ("dbdump", txt);
  fs.chmodSync ("dbdump", 0775);
}



