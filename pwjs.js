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

async function setup_apache (cfg) {
  let conf = "";

  if (cfg.ssl_port != 443) {
    conf += sprintf ("Listen %d\n", cfg.ssl_port);
  }
  conf += sprintf ("<VirtualHost *:%d>\n", cfg.ssl_port);
  conf += sprintf ("  ServerName %s\n", cfg.external_name);
  conf += sprintf ("  SSLEngine on\n");
  conf += sprintf ("  SSLCertificateFile %s\n", cfg.crt_file);
  conf += sprintf ("  SSLCertificateKeyFile %s\n", cfg.key_file);
  if (cfg.chain_file)
    conf += sprintf ("  SSLCertificateChainFile %s\n", cfg.chain_file);

  const www_dir = sprintf ("/var/www/%s", cfg.siteid);

  if (! fs.existsSync (www_dir)) {
    printf ("sudo ln -s %s %s\n", cfg.document_root, www_dir);
  }

  conf += sprintf ("  php_flag display_errors on\n");
  conf += sprintf ("  DocumentRoot %s/public\n", www_dir);
  conf += sprintf ("  SetEnv APP_ROOT %s\n", cfg.srcdir);
  conf += sprintf ("  <Directory %s>\n", www_dir);
  conf += sprintf ("    RewriteEngine on\n");
  conf += sprintf ("    RewriteCond %%{REQUEST_FILENAME} !-d\n");
  conf += sprintf ("    RewriteCond %%{REQUEST_FILENAME} !-f\n");
  conf += sprintf ("    RewriteRule ^.*$ index.php\n");
  conf += sprintf ("    <FilesMatch '\.(html|css|js)'>\n");
  conf += sprintf ("      Header set Cache-Control 'no-cache,"+
		   " no-store, must-revalidate'\n");
  conf += sprintf ("      Header set Pragma 'no-cache'\n");
  conf += sprintf ("      Header set Expires 0\n");
  conf += sprintf ("    </FilesMatch>\n");
  conf += sprintf ("  </Directory>\n");
  conf += sprintf ("  DirectoryIndex index.php\n");
  conf += sprintf ("</VirtualHost>\n");
  
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

async function install_site () {
  const cfg = get_cfg ();

  if (! cfg.options.site_type) {
    printf ("cfg.options.site_type not defined\n");
    process.exit (1);
  }

  let external_name;
  let port_base;
  const nat_info = slurp_file ("/etc/apache2/NAT_INFO");
  if (nat_info) {
    const arr = nat_info.split (" ");
    external_name = arr[0];
    port_base = parseInt (arr[1]);
  } else {
    external_name = "localhost";
    port_base = 8000;
  }

  cfg.srcdir = process.cwd ();

  if (! cfg.site_name) {
    cfg.site_name = path.basename (cfg.srcdir);
  }

  if (! cfg.conf_key) {
    cfg.conf_key = path.basename (os.homedir ());
  }    

  if (! cfg.ssl_port) {
    cfg.ssl_port = get_free_port (port_base);
  }

  cfg.external_name = external_name;

  if (cfg.external_name.match (new RegExp ("[.].*[.]"))) {
    /* two dots in name */
    const local_name = cfg.external_name.replace (new RegExp ("^[^.]*"), 
						  "local");
    cfg.local_url = sprintf ("https://%s:%d/", local_name, cfg.ssl_port);
  }

  cfg.srcdir = process.cwd ();
  cfg.siteid = sprintf ("%s-%s", cfg.site_name, cfg.conf_key);
  cfg.ssl_url = sprintf ("https://%s:%d/", cfg.external_name, cfg.ssl_port);

  cfg.document_root = cfg.srcdir + "/public";
  
  const cert_dir = "/etc/apache2";

  let cert_base = cfg.external_name;

  let crt_file = sprintf ("%s/%s.crt", cert_dir, cert_base);
  if (slurp_file (crt_file) == "") {
    if (cfg.external_name.match (new RegExp ("[.].*[.]"))) {
      /* 2 dots, like www.example.com, replace the first word */
      cert_base = cfg.external_name.replace (new RegExp ("^[^.]*"), 
					     "wildcard");
    } else {
      /* 1 dot, like example.com, prepend wildcard */
      cert_base = sprintf ("wildcard.%s", cfg.external_name);
    }
    crt_file = cert_dir + "/" + cert_base + ".crt";
  }
  
  cfg.crt_file = crt_file;
  cfg.key_file = sprintf ("%s/%s.key", cert_dir, cert_base);
  
  let chain_file = sprintf ("%s/%s.chain.pem", cert_dir, cert_base);
  if (slurp_file (chain_file) != "") {
    cfg.chain_file = chain_file;
  } else {
    chain_file = sprintf ("%s/%s.ca-bundle", cert_dir, cert_base);
    if (slurp_file (chain_file) != "") {
      cfg.chain_file = chain_file;
    }
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

  printf ("%s\n", cfg.ssl_url);
  if (cfg.local_url)
    printf ("%s\n", cfg.local_url);

  if (cfg.options.example_path) {
    printf ("%s%s\n", cfg.ssl_url, cfg.options.example_path);
  }

  const tmpname = "TMP.cfg";
  fs.writeFileSync (tmpname, JSON.stringify (cfg , null, "\t") + "\n");
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

page_func (async function query_raw (stmt, params = []) {
  let pool = get_db_pool ();

  if (db_client == null) {
    db_client = await db_pool.connect ();
  }

  if (! in_transaction) {
    await db_client.query ("begin");
    in_transaction = true;
  }

  return (db_client.query (stmt, params));
});

page_func (function get_head_commit () {
  let head = slurp_file (".git/HEAD");
  let matches = /^ref: (.*)/.exec (head);
  if (matches) {
    head = slurp_file (sprintf (".git/%s", matches[1]));
  }
  head = head.trim();
  if (! head.match(/^[0-9a-f]{40}$/))
    return null;
  
  return (head);
});

page_func (async function query (stmt, params = []) {
  let res = await p.query_raw (stmt, params);
  return (res.rows);
});

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

async function do_rollback () {
  if (db_client) {
    if (in_transaction) {
      await db_client.query ("rollback");
      in_transaction = false;
    }
    db_client.release ();
    db_client = null;
  }
}  

async function setup_postgres (cfg) {
  let pool = new Pool ({
    host: '/var/run/postgresql',
    database: 'template1'
  });

  let res = await pool.query ("select 0"
			      +" from pg_database"
			      +" where datname = $1",
			      [cfg.siteid]);
  if (res.rows.length == 0) {
    printf ("createdb %s\n", cfg.siteid);
  }

  pool.end();

  knex_setup_postgres (cfg);
}

function knex_setup_postgres (cfg) {
  if (! fs.existsSync ("knexfile.js")) {
    var opts = {};
    opts.client ="pg";
    opts.connection = {
      "host": "/var/run/postgresql",
      "database": cfg.siteid
    };
    
    fs.writeFileSync ("knexfile.js",
		      "module.exports = " +
		      JSON.stringify (opts , null, "\t") +
		      "\n");
  }
   
  if (! fs.existsSync ("migrations")) {
    printf ("knex migrate:make start\n");
  }
}

let ws_clients = [];

function ws_client_setup (client) {
  console.log ("ws client connected");
  ws_clients.push (client);
  client.on ('close', () => {
    console.log ("ws client disconnected");
    ws_clients = ws_clients.filter (c => c !== client);
    console.log ("client count " + ws_clients.length);
  });

  let msg = { "foo": "bar" };
  client.send (JSON.stringify (msg));
}

function ws_notify (msg) {
  if (ws_clients.length > 0) {
    const msg_str = JSON.stringify (msg);
    for (let client of ws_clients) {
      client.send (msg_str);
    }
  }
}
exports.ws_notify = ws_notify;

function make_app () {
  let cfg = get_cfg ();
  
  app = express();

  app.use(express.static('public'));

  app.disable('etag');

  app.use(session({
    store: new pgSession ({ pool: get_db_pool () }),
    secret: cfg.session_secret,
    resave: false,
    cookie: { expires: false },
    saveUninitialized: false
  }));
  
  let key = fs.readFileSync (cfg.key_file, "utf8");
  let crt = fs.readFileSync (cfg.crt_file, "utf8");
  if (cfg.chain_file) {
    let chain = fs.readFileSync (cfg.chain_file, "utf8");
    crt = sprintf ("%s\n%s\n", crt.trim (), chain.trim ());
  }

  server = https.createServer ({ key: key, cert: crt }, app);

  wss = new ws.Server({ server });

  wss.on ('connection', ws_client_setup);
  
  server.listen (cfg.ssl_port);

  printf ("%s\n", cfg.ssl_url);
  if (cfg.local_url)
    printf ("%s\n", cfg.local_url);

  exports.app = app;
  exports.server = server;

  return ({
    app: app,
    server: server
  });
}
exports.make_app = make_app;

function build_page (pg) {
  let ret = "";

  ret += "<!DOCTYPE html>\n";
  ret += "<html>\n";
  ret += "  <head>\n";
  ret += "    <title>test</title>\n";

  let target = sprintf ("style.css?c=%s", p.cache_defeater);
  ret += sprintf ("<link href='%s' rel='stylesheet' />\n", p.fix_target(target));

  if (pg.head_scripts) {
    for (let idx in pg.head_scripts) {
      let head_script = pg.head_scripts[idx];
      ret += sprintf (" <script type='text/javascript' src='%s'></script>\n",
		      p.h(head_script));
    }
  }

  ret += "  </head>\n";

  let attrs = "";
  if (pg.body_id)
    attrs += sprintf (" id='%s'", pg.body_id);
  ret += sprintf ("  <body %s>\n", attrs);
  ret += pg.body;
  ret += "  </body>\n";
  ret += "</html>\n";

  return (ret);
}

async function get_handler (req, res, page_module) {
  res.set ('Cache-Control', 'max-age=0, no-cache');
  try {
    const pg = await page_module.exports.make_page(req, res);
    res.send (build_page (pg));

  } catch (e) {
    throw e;
  }
}

exports.register_page = function (filename, page_module) {
  let pagename = path.basename (filename, ".js");
  const url_path = sprintf ("/%s", pagename);

  app.get(url_path, async (req, res) => {
    console.log ("serve", url_path);
    try {
      await get_handler (req, res, page_module);
    } catch (e) {
      await do_rollback ();
      let msg = sprintf ("<h3>%s</h3><pre>%s</pre>\n",
			 p.h(e.message),
			 p.h(e.stack));
      res.send (msg);
    }
    await do_commit ();
  });

  return (exports);
}

function page_func (func) {
  exports[func.name] = func;
  p[func.name] = func;
}

page_func (function put (str) {
  this.body += str;
});

page_func (function h (str) {
  return (he.encode (str));
});

page_func (function head_script (url) {
  this.head_scripts.push (url);
});


page_func (function mklink (text, target) {
  text = text.trim();
  if (text == "")
    return ("");
  target = target.trim();
  if (target == "")
    return (p.h(target));
  return (sprintf ("<a href='%s'>%s</a>",
		   p.fix_target (target), p.h(text)));
});

page_func (function fix_target (path) {
  return path.replace (/\&/g, '&amp;', path);
});

page_func (function rawurlencode (str) {
  return (encodeURIComponent (str));
});

page_func (async function getvar (name) {
  let rows = await p.query ("select val from vars where var = $1", [name]);
  let val = "";
  if (rows.length)
    val = rows[0].val;
  return (val);
});

page_func (async function setvar (name, val) {
  let res = await p.query_raw ("update vars set val = $1 where var = $2",
			     [val, name]);
  if (res.rowCount == 0) {
    p.query ("insert into vars (val, var) values ($1, $2)", [val, name]);
  }
});

page_func (async function get_seq () {
  let rows = await p.query ("select nextval('seq') as seq");
  return (parseInt (rows[0].seq));
});

