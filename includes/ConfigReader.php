<?php
/**
 * FreePBX Config Reader
 *
 * Reads FreePBX configuration files to extract database and AMI credentials.
 * Used by the background worker which runs outside the FreePBX framework.
 */

namespace FreePBX\modules\Donglemanager;

class ConfigReader
{
    /**
     * Read FreePBX configuration and return credentials array
     *
     * @return array Contains: db_host, db_user, db_pass, db_name, ami_user, ami_pass
     * @throws \Exception If configuration cannot be read
     */
    public static function readConfig(): array
    {
        $config = [
            'db_host' => 'localhost',
            'db_user' => 'freepbxuser',
            'db_pass' => '',
            'db_name' => 'asterisk',
            'ami_host' => '127.0.0.1',
            'ami_port' => 5038,
            'ami_user' => 'admin',
            'ami_pass' => 'amp111',
        ];

        // Try /etc/freepbx.conf first (FreePBX 14+)
        $freepbxConf = '/etc/freepbx.conf';
        if (file_exists($freepbxConf) && is_readable($freepbxConf)) {
            $confContent = file_get_contents($freepbxConf);

            // Parse PHP define statements
            $config = array_merge($config, self::parsePhpDefines($confContent));
        }

        // Fallback to /etc/amportal.conf
        $amportalConf = '/etc/amportal.conf';
        if (file_exists($amportalConf) && is_readable($amportalConf)) {
            $confContent = file_get_contents($amportalConf);
            $config = array_merge($config, self::parseAmportalConf($confContent));
        }

        // Also check FreePBX settings file
        $settingsFile = '/etc/freepbx_settings.php';
        if (file_exists($settingsFile) && is_readable($settingsFile)) {
            $confContent = file_get_contents($settingsFile);
            $config = array_merge($config, self::parsePhpDefines($confContent));
        }

        return $config;
    }

    /**
     * Parse PHP define() statements from configuration content
     */
    private static function parsePhpDefines(string $content): array
    {
        $config = [];

        // Match define('AMPDBHOST', 'value'); style statements
        if (preg_match_all("/define\s*\(\s*['\"](\w+)['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/", $content, $matches)) {
            for ($i = 0; $i < count($matches[1]); $i++) {
                $key = $matches[1][$i];
                $value = $matches[2][$i];

                switch ($key) {
                    case 'AMPDBHOST':
                        $config['db_host'] = $value;
                        break;
                    case 'AMPDBUSER':
                        $config['db_user'] = $value;
                        break;
                    case 'AMPDBPASS':
                        $config['db_pass'] = $value;
                        break;
                    case 'AMPDBNAME':
                        $config['db_name'] = $value;
                        break;
                    case 'AMPMGRUSER':
                        $config['ami_user'] = $value;
                        break;
                    case 'AMPMGRPASS':
                        $config['ami_pass'] = $value;
                        break;
                    case 'ASTMANAGERPORT':
                        $config['ami_port'] = (int)$value;
                        break;
                }
            }
        }

        return $config;
    }

    /**
     * Parse key=value format from amportal.conf
     */
    private static function parseAmportalConf(string $content): array
    {
        $config = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and empty lines
            if (empty($line) || $line[0] === '#') {
                continue;
            }

            // Parse key=value
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                switch ($key) {
                    case 'AMPDBHOST':
                        $config['db_host'] = $value;
                        break;
                    case 'AMPDBUSER':
                        $config['db_user'] = $value;
                        break;
                    case 'AMPDBPASS':
                        $config['db_pass'] = $value;
                        break;
                    case 'AMPDBNAME':
                        $config['db_name'] = $value;
                        break;
                    case 'AMPMGRUSER':
                        $config['ami_user'] = $value;
                        break;
                    case 'AMPMGRPASS':
                        $config['ami_pass'] = $value;
                        break;
                }
            }
        }

        return $config;
    }
}
