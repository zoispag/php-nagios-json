<?php

class NagiosParser
{
    private $statusFile = './status.dat';
    private $skipHosts = [
        'localhost',
    ];

    public function __construct()
    {
        date_default_timezone_set('Europe/Amsterdam');
    }

    public function getData()
    {
        $info = [];
        foreach (self::parseNagiosFile($this->statusFile) as $host => $services) {
            if (in_array($host, $this->skipHosts)) {
                continue;
            }

            $info[$host] = [
                'host'       => $host,
                'branch'     => $services['Check Branch']['plugin_output'],
                'stage'      => $services['Release stage']['plugin_output'],
                'version'    => explode(' ', $services['version']['plugin_output'])[1],
                'timestamp'  => $last_check = $services['Uptime']['last_check'],
                'last_check' => date('Y-m-d H:i:s', $last_check),
            ];
        }

        return $info;
    }

    private static function parseNagiosFile($statusFile)
    {
        # variables to keep state
        $inSection = false;
        $sectionType = '';
        $sectionData = $serviceStatus = $typeTotals = [];

        $fh = fopen($statusFile, 'r');
        # loop through the file
        while ($line = fgets($fh)) {
            $line = trim($line); // strip whitespace
            if ($line == '') {
                continue;
            } // ignore blank line
            if (substr($line, 0, 1) == '#') {
                continue;
            } // ignore comment

            // ok, now we need to deal with the sections
            if (!$inSection) {
                // we're not currently in a section, but are looking to start one
                if (substr($line, strlen($line) - 1, 1) == '{') {
                    // space and ending with {, so it's a section header
                    $sectionType = substr($line, 0, strpos($line, ' ')); // first word on line is type
                    $inSection = true;
                    // we're now in a section
                    $sectionData = [];

                    // increment the counter for this sectionType
                    if (isset($typeTotals[$sectionType])) {
                        $typeTotals[$sectionType] = $typeTotals[$sectionType] + 1;
                    } else {
                        $typeTotals[$sectionType] = 1;
                    }
                }
            } elseif ($inSection && trim($line) == '}') {
                // closing a section
                if ($sectionType == 'servicestatus') {
                    $serviceStatus[$sectionData['host_name']][$sectionData['service_description']] = $sectionData;
                }

                $inSection = false;
                $sectionType = '';
                continue;
            } else {
                // we're currently in a section, and this line is part of it
                $lineKey = substr($line, 0, strpos($line, '='));
                $lineVal = substr($line, strpos($line, '=') + 1);

                // add to the array as appropriate
                if ($sectionType == 'servicestatus') {
                    $sectionData[$lineKey] = $lineVal;
                }
                // else continue on, ignore this section, don't save anything
            }
        }

        fclose($fh);

        return $serviceStatus;
    }
}

$nagios = new NagiosParser;
$info = $nagios->getData();

echo json_encode($info);
