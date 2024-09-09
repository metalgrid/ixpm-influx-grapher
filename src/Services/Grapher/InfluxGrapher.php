<?php

namespace Metalgrid\InfluxGrapher\Services\Grapher;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use IXP\Services\Grapher\{
    Backend as GrapherBackend,
    Graph,
};
use IXP\Contracts\Grapher\Backend as GrapherBackendContract;

class InfluxGrapher extends GrapherBackend implements GrapherBackendContract
{
    public function name(): string
    {
        return 'influx';
    }

    public function isMonolithicConfigurationSupported(): bool
    {
        return false;
    }

    public function isMultiFileConfigurationSupported(): bool
    {
        return false;
    }

    public function generateConfiguration(int $type = self::GENERATED_CONFIG_TYPE_MONOLITHIC, array $options = []): array
    {
        return [];
    }

    public function dataPath(Graph $graph): string
    {
        return "";
    }

    public function rrd(Graph $graph): string
    {
        return "";
    }

    public function png(Graph $graph): string
    {
        return "";
    }

    public function isConfigurationRequired(): bool
    {
        return false;
    }

    public static function supports(): array
    {
        $graphProtocols = Graph::PROTOCOLS;
        unset($graphProtocols[Graph::PROTOCOL_ALL]);

        return [
            'vlan' => [
                'protocols'   => Graph::PROTOCOLS_REAL,
                'categories'  => [
                    Graph::CATEGORY_BITS => Graph::CATEGORY_BITS,
                    Graph::CATEGORY_PACKETS => Graph::CATEGORY_PACKETS
                ],
                'periods'     => Graph::PERIODS,
                'types'       => Graph::TYPES,
            ],
            'vlaninterface' => [
                'protocols'   => $graphProtocols,
                'categories'  => [
                    Graph::CATEGORY_BITS => Graph::CATEGORY_BITS,
                    Graph::CATEGORY_PACKETS => Graph::CATEGORY_PACKETS
                ],
                'periods'     => Graph::PERIODS,
                'types'       => Graph::TYPES,
            ],
            'p2p' => [
                'protocols'   => $graphProtocols,
                'categories'  => [
                    Graph::CATEGORY_BITS => Graph::CATEGORY_BITS,
                    Graph::CATEGORY_PACKETS => Graph::CATEGORY_PACKETS
                ],
                'periods'     => Graph::PERIODS,
                'types'       => [Graph::TYPE_JSON => Graph::TYPE_JSON],
            ],
        ];
    }

    public function data(Graph $graph): array
    {

        [
            'type' => $type, // ?
            'category' => $category, // bits/packets
            'period' => $period,
            'protocol' => $protocol,
            'svli' => $srcvli,
            'dvli' => $dstvli,
        ] = $graph->getParamsAsArray();

        [
            'url' => $url,
            'username' => $username,
            'password' => $password,
            'database' => $db,
            'measurement' => $measurement,
            'retention-policy' => $rp,
        ] = config('grapher.backends.influxdb');

        $now = Carbon::now();
        $now->minute($now->minute - $now->minute % 5);
        $now->second(0);
        $to = $now->getTimestampMs();
        $aggregate = "5m";
        switch ($period) {
            case 'day':
                $from = $now->subDay()->getTimestampMs();
                break;
            case 'week':
                $from = $now->subWeek()->getTimestampMs();
                break;
            case 'month':
                $from = $now->subMonth()->getTimestampMs();
                break;
            case 'year':
                $from = $now->subYear()->getTimestampMs();
                $aggregate = "24h";
                break;
            default:
                throw new \DateException("Invalid period: {$period}");
        }

        $query = <<<END_QUERY
        SELECT
            mean({$category})
        FROM
            "$rp"."$measurement"
        WHERE
                dstvli::tag = '$dstvli'
            AND
                srcvli::tag = '$srcvli'
            AND
                protocol::tag = '$protocol'
            AND
                time > {$from}
            AND
                time < {$to}
        GROUP BY
            time($aggregate)
        END_QUERY;


        $url = "{$url}/query";
        $q = http_build_query([
            'db' => $db,
            'q' => $query
        ]);
        try {
            $response = Http::withBasicAuth($username, $password)->get($url . '?' . $q);
            $data = $response->json();

            $timestamps = [];
            $bits = [];

            foreach ($data['results'][0]['series'] as $series) {
                $points = $series['values'];

                foreach ($points as $point) {
                    $timestamps[] = $point[0];
                    $bits[] = $point[1];
                }
            }

            return json_encode([
                "labels" => $timestamps,
                "datasets" => $protocol,
                "data" => $bits,
                "backgroundColor" => "rgba(255, 99, 132, 0.2)",
                "borderColor" => "rgba(255,88,132,1)",
                "borderWidth" => 1,
                "fill" => true,
                "stack" => "stacked"
            ]);
        } catch (Exception $e) {
            Log::notice("[Grapher] {$this->name()} data(): could not fetch graph data: {$e}");
            return [];
        }
    }
}
