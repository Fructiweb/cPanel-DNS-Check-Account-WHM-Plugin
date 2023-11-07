<?php

@include_once('/usr/local/cpanel/php/WHM.php');
if (class_exists(class: WHM::class)) {
	WHM::header('', 0, 0);
}

$localdomain = '/etc/localdomains';
$userdatadomains = '/etc/userdatadomains';
$ini_set = 0;

if (file_exists('.env')) {
	$localdomain = 'localdomains';
	$userdatadomains = 'userdatadomains';
	$ini_set = 1;
}

ini_set('display_errors', $ini_set);

function resolve_domain($domain): array
{
	$dns = '8.8.8.8';  // Google Public DNS
	if (rand(0, 1) == 1) {
		$dns = '208.67.222.222'; // Open DNS
	}
	$ip = `nslookup $domain $dns`; // the backticks execute the command in the shell
	$ips = [];
	if (preg_match_all('/Address: ((?:\d{1,3}\.){3}\d{1,3})/', $ip, $match) > 0) {
		$ips = $match[1];
	}
	return $ips;
}

function open_file_per_line($file): bool|array
{
	@$handle = fopen($file, "r");
	if ($handle) {
		$lines = [];
		while (($line = fgets($handle)) !== false) {
			$lines[] = trim($line);
		}
		return $lines;
		fclose($handle);
	} else {
		return false;
	}
}

function check_valid_resolve_ip($ip, $domain): array
{
	if ($domain === '_SERVER_HOSTNAME_') {
		return ['label' => 'info', 'msg' => ''];
	}
	if (!filter_var($ip, FILTER_VALIDATE_IP)) {
		return ['label' => 'danger', 'msg' => 'Invalid IP'];
	}
	$domain_local_ip = get_domain_ip_local_file($domain);
	if ($domain_local_ip['ip'] !== $ip) {
		return ['label' => 'danger', 'msg' => 'Different IP'];
	}
	return ['label' => 'success', 'msg' => ''];
}

function get_domain_ip_local_file($domain): array
{
	global $userdatadomains;

	$file_lines = open_file_per_line($userdatadomains);
	$file_ip_nat_lines = open_file_per_line('/var/cpanel/cpnat');
	$file_ip_nat_lines = is_bool($file_ip_nat_lines) ? [] : $file_ip_nat_lines;
	foreach ($file_lines as $line) {
		$explode = explode('==', $line);
		$explode_two = explode(':', $explode[0]);
		if (trim($explode_two[0]) === trim($domain)) {
			$ip_port = $explode[5];
			$explode_ip = explode(':', $ip_port);
			foreach ($file_ip_nat_lines as $line_ip_nat) {
				$explode_ip_nat = explode(' ', $line_ip_nat);
				if ($explode_ip_nat[0] == $explode_ip[0]) {
					$explode_ip[0] = $explode_ip_nat[1];
				}
			}
			return ['ip' => $explode_ip[0], 'acc' => trim($explode_two[1]), 'reseller' => trim($explode[1]), 'type' => trim($explode[2])];
		}
	}
	return [];
}

$all_suspended_users = json_decode(shell_exec('/usr/local/cpanel/bin/whmapi1 listsuspended --output=jsonpretty'), true);
$all_domains_local = open_file_per_line($localdomain);
$hostname = gethostname();

?>
<title>DNS Check Account</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.1/dist/darkly/bootstrap.min.css"
      integrity="sha256-jBk81HSkRnnLLjgZa5W96w8mjNj/WyoBFo7sbLvn9kg=" crossorigin="anonymous">
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <h1>cPanel DNS Check Account WHM Plugin</h1>
                <p>Data are processed every 24h by compiling a static html file upon first access. (this can be
                    triggered by a cron job)</p>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="row mx-auto">
            <div class="mt-2 mb-2 mx-auto">
                <div class="col-4 form-group mx-auto text-center">
                    <div class="form-floating">
                        <input id="filterList" class="form-control rounded" placeholder=" ">
                        <label for="filterList">Recherche</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <table class="table">
                    <thead>
                    <tr>
                        <td>User</td>
                        <td>Reseller User</td>
                        <td>Domain</td>
                        <td>Local IP</td>
                        <td></td>
                    </tr>
                    </thead>
                    <tbody>
					<?php

					$json = [];
					@$json = file_get_contents('dns-check.json');

					if (is_string($json)) {
						$json = json_decode($json, true);
					}

					$timestamp = $json['timestamp'] ?? 0;

					if (time() - $timestamp > 86400) {
						$json = [];
					}

					foreach ($all_domains_local as $domain) {
						$domain_local_acc = get_domain_ip_local_file($domain);

						if (empty($domain_local_acc['type'])) {
							continue;
						}

						if ($domain_local_acc['type'] === 'sub' || $domain_local_acc['type'] === 'main' || empty($domain_local_acc['acc'])) {
							continue;
						}

						$is_suspended = false;

						if ($all_suspended_users) {
							$is_suspended = in_array($domain_local_acc['acc'], array_column($all_suspended_users['data']['account'], 'user'));
						}

						$resolve_ips = $json[$domain] ?? '';

						if (!$resolve_ips) {
							$resolve_ips = resolve_domain($domain);
							$json[$domain] = ['ip' => $resolve_ips];
						} else {
							$resolve_ips = $resolve_ips['ip'];
						}

						$ips_ = '';

						foreach ($resolve_ips as $ip) {
							if ($domain === $hostname) {
								$domain = '_SERVER_HOSTNAME_';
							}

							$check = check_valid_resolve_ip($ip, $domain);
							$ips_ .= '<span class="my-auto alert alert-' . $check['label'] . '">' . $ip . '</span> ' . $check['msg'] . '<br><br>';
						}
						$ips = rtrim($ips_, '<br>');
						$ip_result_html = $ips !== '' ? $ips : '<span class="alert alert-danger my-auto">Not Resolve</span>';
						if ($domain === '_SERVER_HOSTNAME_') {
							$domain = $hostname;
							$domain_local_acc['acc'] = 'root';
						}

						if ($is_suspended) {
							$login_link = '<a class="btn btn-danger rounded disabled" href="">Suspended</a>';
						} else {
							$login_link = '';
							$shell_link_command_output = shell_exec('/usr/local/cpanel/bin/whmapi1 --output=jsonpretty create_user_session user=' . $domain_local_acc['acc'] . ' service=cpaneld');

							$pattern_cloud = "/cloud\d+/";
							$pattern_domain = "/(\w+)fructiweb/";
							$replacement_cloud = "$0.";

							$fructiweb_cloud_domain = preg_replace($pattern_cloud, $replacement_cloud, $domain_local_acc['sub']);
							$fructiweb_cloud_domain = preg_replace($pattern_domain, '', $fructiweb_cloud_domain);
							$fructiweb_cloud_domain .= '.fr';

							if (!empty($shell_link_command_output)) {
								$shell_link_command_output = json_decode($shell_link_command_output, true);
							}

							if (!empty($shell_link_command_output['data']['url'])) {
								$default_domain_name = parse_url($shell_link_command_output['data']['url'], PHP_URL_HOST);
								$login_link = str_replace($default_domain_name, $fructiweb_cloud_domain, $shell_link_command_output['data']['url']);
								$login_link = '<a class="btn btn-info rounded" href="' . $login_link . '" target="_blank">Connexion</a>';
							}
						}
						?>
                        <tr>
                            <td><?= $domain_local_acc['acc'] ?></td>
                            <td><?= $domain_local_acc['reseller'] ?></td>
                            <td>(<?= $domain_local_acc['type'] ?>) <a class="btn btn-info"
                                                                      href="https://<?= $domain ?>/"
                                                                      target="_blank"><?= $domain ?></a>
                            </td>
                            <td><?= $domain_local_acc['ip'] ?></td>
                            <td><?= $ip_result_html ?><br></td>
                            <td><?= $login_link ?><br></td>
                        </tr>
						<?php
					}
					$json['timestamp'] = time();
					$json = json_encode($json);
					file_put_contents('dns-check.json', $json);
					?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>

<script src="https://code.jquery.com/jquery-3.7.0.slim.min.js"
        integrity="sha256-tG5mcZUtJsZvyKAxYLVXrmjKBVLd6VpVccqz/r4ypFE=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"
        integrity="sha384-oBqDVmMz9ATKxIep9tiCxS/Z9fNfEXiDAYTujMAeBAsjFuCZSmKbSSUnQlmh/jp3"
        crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.min.js"
        integrity="sha384-cuYeSxntonz0PPNlHhBs68uyIAVpIIOZZ5JqeqvYYIcEL727kskC66kF92t6Xl2V"
        crossorigin="anonymous"></script>


<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('filterList').addEventListener('keyup', (e) => {
            filterList(e.target.value);
        });
    });

    function filterList(t) {
        let filter, row, i, txtValue;

        filter = t.toUpperCase();
        row = document.querySelectorAll('tr');

        for (i = 0; i < row.length; i++) {
            const tdRow = row[i].querySelectorAll('td');

            for (let j = 0; j < tdRow.length; j++) {
                txtValue = tdRow[j].textContent || tdRow[j].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    row[i].style.display = "";
                    break;
                } else {
                    row[i].style.display = "none";
                }
            }
        }
    }
</script>
