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
    private $protocol = [
        'ipv4' => 'IPv4',
        'ipv6' => 'IPv6',
    ];

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
                'types'       => [
                    Graph::TYPE_JSON => Graph::TYPE_JSON,
                    Graph::TYPE_LOG => Graph::TYPE_LOG
                ],
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

        $protocol = $this->protocol[$protocol];

        [
            'url' => $url,
            'username' => $username,
            'password' => $password,
            'database' => $db,
            'measurement' => $measurement,
            'retention-policy' => $rp,
        ] = config('grapher.backends.influx');

        $now = Carbon::now();
        $now->minute($now->minute - $now->minute % 5);
        $now->second(0);
        $to = $now->toISOString();
        $aggregate = "5m";
        switch ($period) {
            case 'day':
                $from = $now->subDay()->toISOString();
                break;
            case 'week':
                $from = $now->subWeek()->toISOString();
                break;
            case 'month':
                $from = $now->subMonth()->toISOString();
                break;
            case 'year':
                $from = $now->subYear()->toISOString();
                $aggregate = "24h";
                break;
            default:
                throw new \DateException("Invalid period: {$period}");
        }

        // srcvli and dstvli are reversed because ... reasons.
        $query = <<<END_QUERY
        SELECT
            mean({$category})
        FROM
            "$rp"."$measurement"
        WHERE
                srcvli::tag = '$dstvli'
            AND
                dstvli::tag = '$srcvli'
            AND
                protocol::tag = '$protocol'
            AND
                time > '{$from}'
            AND
                time < '{$to}'
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
            if ($response->failed()) {
                Log::warning("[Grapher] {$this->name()} data(): InfluxDB request failed: {$response->status()}");
                return [];
            }

            $data = $response->json();
            $timestamps = [];
            $bits = [];

            if (!array_key_exists('series', $data['results'][0])) {
                Log::warning("[Grapher] {$this->name()} data(): InfluxDB response has no series!");
                Log::notice("[Influx] Query: {$query}");
                return [];
            }

            foreach ($data['results'][0]['series'] as $series) {
                $points = $series['values'];

                foreach ($points as $point) {
                    $timestamps[] = $point[0];
                    $bits[] = $point[1];
                }
            }

            return [
                "labels" => $timestamps,
                "datasets" => [[
                    "label" => $protocol,
                    "data" => $bits,
                    "backgroundColor" => "rgba(115, 191, 105, 0.2)",
                    "borderColor" => "rgba(115, 191, 105,1)",
                    "borderWidth" => 1,
                    "fill" => true,
                    "stack" => "stacked"
                ]]
            ];
        } catch (Exception $e) {
            Log::notice("[Grapher] {$this->name()} data(): could not fetch graph data: {$e}");
            return [];
        }
    }
}
