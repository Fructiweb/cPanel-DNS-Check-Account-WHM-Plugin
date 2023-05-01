# cPanel-DNS-Check-Account-WHM-Plugin

### Install

`mkdir -p /usr/local/cpanel/whostmgr/docroot/cgi/cpanel-account-dns-check`<br>
`cd /usr/local/cpanel/whostmgr/docroot/cgi/cpanel-account-dns-check/`<br>
`wget --no-check-certificate -O master.zip https://github.com/felipegabriel/cPanel-DNS-Check-Account-WHM-Plugin/archive/master.zip`<br>
`unzip master.zip`<br>
`/bin/cp -rf /usr/local/cpanel/whostmgr/docroot/cgi/cpanel-account-dns-check/cPanel-DNS-Check-Account-WHM-Plugin-master/* /usr/local/cpanel/whostmgr/docroot/cgi/cpanel-account-dns-check/`<br>
`/bin/rm -rvf /usr/local/cpanel/whostmgr/docroot/cgi/cpanel-account-dns-check/cPanel-DNS-Check-Account-WHM-Plugin-master`<br>
`/bin/rm -f /usr/local/cpanel/whostmgr/docroot/cgi/cpanel-account-dns-check/master.zip`<br>
`/usr/local/cpanel/bin/register_appconfig /usr/local/cpanel/whostmgr/docroot/cgi/cpanel-account-dns-check/cpanel-account-dns-check.conf`	<br>

Used by
	brasilwork.com.br
