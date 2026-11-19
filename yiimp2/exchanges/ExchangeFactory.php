<?php

namespace app\exchanges;

use app\exchanges\drivers\{
    BiboxDriver, BinanceDriver, BitstampDriver, CexIoDriver,
    ExbitronDriver, GateIoDriver, HitBtcDriver, KlingexDriver,
    KrakenDriver, KuCoinDriver, NestexDriver, NonKycDriver,
    PoloniexDriver, SafeTradeDriver, ShapeShiftDriver, YobitDriver
};

/**
 * Creates and filters exchange driver instances.
 *
 * Registry order matters: it controls the execution order when iterating
 * withBalance() (bitstamp → cexio → kraken → poloniex) and
 * withTrading() (binance → kucoin → yobit → nestex → nonkyc → exbitron),
 * withMarkets/withDiscover() includes klingex (markets + discover, USDT pairs).
 */
class ExchangeFactory
{
    private static array $registry = [
        'bitstamp'   => BitstampDriver::class,
        'cexio'      => CexIoDriver::class,
        'kraken'     => KrakenDriver::class,
        'poloniex'   => PoloniexDriver::class,
        'binance'    => BinanceDriver::class,
        'kucoin'     => KuCoinDriver::class,
        'yobit'      => YobitDriver::class,
        'nestex'     => NestexDriver::class,
        'nonkyc'     => NonKycDriver::class,
        'exbitron'   => ExbitronDriver::class,
        'klingex'    => KlingexDriver::class,
        'gateio'     => GateIoDriver::class,
        'hitbtc'     => HitBtcDriver::class,
        'safetrade'  => SafeTradeDriver::class,
        'bibox'      => BiboxDriver::class,
        'shapeshift' => ShapeShiftDriver::class,
    ];

    /**
     * Instantiate a driver by exchange name.
     * Returns a no-op driver for unknown exchange names.
     */
    public static function make(string $name): ExchangeDriver
    {
        $class = self::$registry[$name] ?? null;
        if (!$class) {
            return new class($name) extends ExchangeDriver {
                public function __construct(private readonly string $n) {}
                public function name(): string { return $this->n; }
            };
        }
        return new $class();
    }

    /** All registered driver instances (in registry order). */
    public static function all(): array
    {
        return array_map(fn($c) => new $c(), self::$registry);
    }

    /** Drivers that support market price updates. */
    public static function withMarkets(): array
    {
        return array_values(array_filter(self::all(), fn($d) => $d->supportsMarkets()));
    }

    /** Drivers that support coin discovery (raw coin listing). */
    public static function withDiscover(): array
    {
        return array_values(array_filter(self::all(), fn($d) => $d->supportsDiscover()));
    }

    /** Drivers that support account balance sync (state 1). */
    public static function withBalance(): array
    {
        return array_values(array_filter(self::all(), fn($d) => $d->supportsBalance()));
    }

    /** Drivers that support automated trading (state 2). */
    public static function withTrading(): array
    {
        return array_values(array_filter(self::all(), fn($d) => $d->supportsTrading()));
    }

    /** Register a custom driver class (useful for testing or plugins). */
    public static function register(string $name, string $driverClass): void
    {
        self::$registry[$name] = $driverClass;
    }
}
