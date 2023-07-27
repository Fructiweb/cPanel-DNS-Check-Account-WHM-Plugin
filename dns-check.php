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

$all_suspended_users = json_decode(shell_exec('/usr/local/cpanel/bin/whmapi1 listsuspended'), true);
var_dump($all_suspended_users);
$all_domains_local = open_file_per_line($localdomain);
$hostname = gethostname();
?>
<title>DNS Check Account</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootswatch@4.5.2/dist/darkly/bootstrap.min.css"
      integrity="sha384-nNK9n28pDUDDgIiIqZ/MiyO3F4/9vsMtReZK39klb/MtkZI3/LtjSjlmyVPS3KdN"
      crossorigin="anonymous">
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <h1>cPanel DNS Check Account WHM Plugin</h1>
            </div>
        </div>
    </div>
    <div class="container-fluid">
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
                    foreach ($all_domains_local as $domain) {
                        $domain_local_acc = get_domain_ip_local_file($domain);

                        if ($domain_local_acc['type'] === 'sub' || $domain_local_acc['type'] === 'main' || empty($domain_local_acc['acc'])) {
                            continue;
                        }

                        $resolve_ips = resolve_domain($domain);
                        $ips_ = '';

                        foreach ($resolve_ips as $ip) {
                            if ($domain === $hostname) {
                                $domain = '_SERVER_HOSTNAME_';
                            }
                            $check = check_valid_resolve_ip($ip, $domain);
                            $ips_ .= '<span class="alert alert-' . $check['label'] . '">' . $ip . '</span> ' . $check['msg'] . '<br><br>';
                        }
                        $ips = rtrim($ips_, '<br>');
                        $ip_result_html = $ips !== '' ? $ips : '<span class="alert alert-danger">Not Resolve</span>';
                        if ($domain === '_SERVER_HOSTNAME_') {
                            $domain = $hostname;
                            $domain_local_acc['acc'] = 'root';
                        }

                        $login_link = '';
                        $shell_link_command_output = shell_exec('/usr/local/cpanel/bin/whmapi1 --output=jsonpretty create_user_session user=' . $domain_local_acc['acc'] . ' service=cpaneld');

                        if ($shell_command_output) {
                            $pattern_cloud = "/cloud\d+/";
                            $pattern_domain = "/(\w+)fructiweb/";
                            $replacement_cloud = "$0.";

                            $fructiweb_cloud_domain = preg_replace($pattern_cloud, $replacement_cloud, $domain_local_acc['acc']);
                            $fructiweb_cloud_domain = preg_replace($pattern_domain, '', $fructiweb_cloud_domain);
                            $fructiweb_cloud_domain .= '.fr';

                            $shell_command_output = json_decode($shell_command_output, true);
                            $default_domain_name = parse_url($shell_command_output['data']['url'], PHP_URL_HOST);
                            $login_link = str_replace($default_domain_name, $fructiweb_cloud_domain, $shell_command_output['data']['url']);
                        }
                        ?>
                        <tr>
                            <td><?= $domain_local_acc['acc'] ?></td>
                            <td><?= $domain_local_acc['reseller'] ?></td>
                            <td>(<?= $domain_local_acc['type'] ?>) <?= $domain ?></td>
                            <td><?= $domain_local_acc['ip'] ?></td>
                            <td><?= $ip_result_html ?><br></td>
                            <td><a class="btn btn-info rounded" href="<?= $login_link ?>" target="_blank">Connexion</a>
                                <br></td>
                        </tr>
                        <?php
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>