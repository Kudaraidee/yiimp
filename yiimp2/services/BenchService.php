<?php

namespace app\services;

use Yii;
use app\models\Benchmarks;
use app\models\BenchChips;

/**
 * BenchService — benchmark data cleanup, chip name resolution, and chip record maintenance.
 *
 * Ported from: web/yaamp/core/backend/bench.php → BenchUpdateChips()
 *
 * Note: getChipName() is defined in web/yaamp/modules/bench/functions.php.
 * It is guarded with function_exists() so the cleanup queries still run even
 * when the legacy bench module is not loaded.
 */
class BenchService
{
    /**
     * Clean up invalid benchmark records, assign chip names, and sync the bench_chips table.
     * Ports: BenchUpdateChips()
     */
    public function updateChips(): void
    {
        $db = Yii::$app->db;

        // ------------------------------------------------------------------ //
        // Data quality cleanup

        $db->createCommand("UPDATE benchmarks SET device=TRIM(device) WHERE type='cpu'")->execute();
        $db->createCommand("UPDATE benchmarks SET power=NULL WHERE power<=3")->execute();
        $db->createCommand("UPDATE benchmarks SET plimit=NULL WHERE plimit=0")->execute();
        $db->createCommand("UPDATE benchmarks SET freq=NULL WHERE freq=0")->execute();
        $db->createCommand("UPDATE benchmarks SET memf=NULL WHERE memf=0")->execute();
        $db->createCommand("UPDATE benchmarks SET realmemf=NULL WHERE realmemf<=100")->execute();
        $db->createCommand("UPDATE benchmarks SET realfreq=NULL WHERE realfreq<=200")->execute();
        // Known bad driver versions (nvml 378.x / 381.x on Linux/Windows, fixed in 382.05)
        $db->createCommand("UPDATE benchmarks SET realfreq=NULL WHERE realfreq<=200 AND driver LIKE '% 378.%'")->execute();
        $db->createCommand("UPDATE benchmarks SET realfreq=NULL WHERE realfreq<=200 AND driver LIKE '% 381.%'")->execute();
        $db->createCommand("DELETE FROM benchmarks WHERE device LIKE '%<%' OR client LIKE '%<%'")->execute();

        // ------------------------------------------------------------------ //
        // Resolve chip names for records that have none

        $benchmarks = Benchmarks::find()->where(['or', ['chip' => null], ['chip' => '']])->all();

        foreach ($benchmarks as $bench) {
            if (empty($bench->vendorid) || empty($bench->device)) {
                continue;
            }

            // x16r is non-deterministic — results are inaccurate
            if ($bench->algo === 'x16r') {
                $bench->delete();
                continue;
            }

            // Block records containing script tags (XSS/injection attempt)
            if (str_contains(json_encode($bench->getAttributes()), 'script')) {
                Yii::warning("bench: deleted suspicious record id={$bench->id}", __CLASS__);
                $bench->delete();
                continue;
            }

            // Remove outlier duplicates (>10 identical records from same user)
            $dups = (int) $db->createCommand(
                "SELECT COUNT(id) FROM benchmarks
                 WHERE vendorid=:vid AND client=:client AND os=:os AND driver=:drv
                   AND throughput=:thr AND userid=:uid",
                [
                    ':vid' => $bench->vendorid, ':client' => $bench->client,
                    ':os'  => $bench->os,       ':drv'    => $bench->driver,
                    ':thr' => $bench->throughput, ':uid'  => $bench->userid,
                ]
            )->queryScalar();

            if ($dups > 10 || round((float) $bench->khps, 3) == 0) {
                $bench->delete();
                continue;
            }

            $chip = $this->getChipName($bench->getAttributes());

            if (empty($chip) || $chip === '-') {
                continue;
            }

            $bench->chip = $chip;

            // Remove records whose hashrate is implausibly low vs the algo average
            $rates = $db->createCommand(
                "SELECT AVG(khps) AS avg, COUNT(id) AS cnt FROM benchmarks WHERE algo=:algo AND chip=:chip",
                [':algo' => $bench->algo, ':chip' => $chip]
            )->queryOne();

            $avg = (float) ($rates['avg'] ?? 0);
            $cnt = (int)   ($rates['cnt'] ?? 0);

            if ($cnt > 250) {
                $bench->delete();
                continue;
            }

            if ($cnt > 5 && (float) $bench->khps < $avg / 2) {
                Yii::info("bench: {$bench->device} ignored — bad {$bench->algo} rate {$bench->khps} kHs", __CLASS__);
                $bench->delete();
                continue;
            }

            if ($bench->chip === 'GPU' || $bench->chip === 'Graphics Device') {
                $bench->delete();
                continue;
            }

            Yii::info("bench: {$bench->device} ({$chip})", __CLASS__);
            $bench->save();
        }

        // ------------------------------------------------------------------ //
        // Add new chip records for chips not yet in bench_chips

        $newChips = $db->createCommand(
            "SELECT DISTINCT B.chip, B.type FROM benchmarks B
             WHERE B.chip NOT IN (
                 SELECT DISTINCT C.chip FROM bench_chips C WHERE C.devicetype = B.type
             )"
        )->queryAll();

        foreach ($newChips as $row) {
            if (empty($row['chip']) || empty($row['type'])) {
                continue;
            }
            $chipRecord             = new BenchChips();
            $chipRecord->chip       = $row['chip'];
            $chipRecord->devicetype = $row['type'];

            if ($chipRecord->save()) {
                Yii::info("bench: added {$chipRecord->devicetype} chip {$chipRecord->chip}", __CLASS__);
                $db->createCommand(
                    "UPDATE benchmarks SET idchip=:id WHERE chip=:chip AND type=:type",
                    [':id' => $chipRecord->id, ':chip' => $row['chip'], ':type' => $row['type']]
                )->execute();
            }
        }

        // ------------------------------------------------------------------ //
        // Back-fill idchip for benchmarks that have a chip name but no idchip

        $unlinked = $db->createCommand(
            "SELECT DISTINCT chip, type FROM benchmarks WHERE idchip IS NULL AND chip IS NOT NULL AND chip != ''"
        )->queryAll();

        foreach ($unlinked as $row) {
            $chipRecord = BenchChips::find()
                ->where(['chip' => $row['chip'], 'devicetype' => $row['type']])
                ->one();
            if (!$chipRecord) {
                continue;
            }
            $db->createCommand(
                "UPDATE benchmarks SET idchip=:id WHERE chip=:chip AND type=:type",
                [':id' => $chipRecord->id, ':chip' => $row['chip'], ':type' => $row['type']]
            )->execute();
        }
    }

    // =========================================================================
    // Display helpers (ported from web/yaamp/modules/bench/functions.php)
    // =========================================================================

    public static function formatCudaArch(string $arch): string
    {
        if (is_numeric($arch)) {
            $a = (int) $arch;
            return 'SM ' . floor($a / 100) . '.' . (($a % 100) / 10);
        }
        if (str_contains($arch, '@')) {
            [$a, $b] = array_map('intval', explode('@', $arch, 2));
            return 'SM ' . floor($a / 100) . '.' . (($a % 100) / 10)
                 . '@' . floor($b / 100) . '.' . (($b % 100) / 10);
        }
        return $arch;
    }

    public static function formatGPU(array $row): string
    {
        return strip_tags($row['device'] . self::getProductIdSuffix($row));
    }

    public static function formatDevice(array $row): string
    {
        return $row['type'] === 'gpu' ? self::formatGPU($row) : self::formatCPUPublic($row);
    }

    /** Power cost in mBTC/day for the given wattage. */
    public static function powercostMbtc(float $watts, float $btcusd): float
    {
        $kwh = defined('YIIMP_KWH_USD_PRICE') ? (float) YIIMP_KWH_USD_PRICE : 0.25;
        return $btcusd > 0 ? ($kwh * 24 * $watts) / $btcusd : 0.0;
    }

    private static function getProductIdSuffix(array $row): string
    {
        $known = [
            '1043:8520' => ' Strix', '1043:8508' => ' Strix',
            '1458:362d' => ' OC',    '1458:3649' => ' Black',
            '1458:36ae' => ' 4GB',   '1458:3701' => ' G1',
            '1458:3702' => ' G1',    '1462:3202' => ' Gaming 2G',
            '1462:3160' => ' Gaming', '1462:3170' => ' Gaming',
            '1462:3301' => ' Armor', '1462:3306' => ' Gaming X',
            '1462:3362' => ' Gaming', '19da:1435' => ' Extreme',
            '3842:2744' => ' SC DDR3', '3842:3753' => ' SC',
            '3842:3757' => ' FTW',   '3842:2951' => ' SC',
            '3842:2956' => ' SC+',   '3842:2957' => ' SSC',
            '3842:2958' => ' FTW',   '3842:2962' => ' SC',
            '3842:2966' => ' SSC',   '3842:3966' => ' SSC 4GB',
            '3842:2974' => ' SC',    '3842:2978' => ' FTW',
            '3842:3975' => ' SSC',   '3842:2983' => ' SC',
            '3842:2986' => ' FTW',   '3842:2989' => ' Hydro',
            '3842:4995' => ' SC+',   '3842:1996' => ' Hybrid',
            '3842:6173' => ' SC',    '3842:6276' => ' FTW',
        ];
        $vid = $row['vendorid'] ?? '';
        if (isset($known[$vid])) return $known[$vid];
        $suffix = \Yii::$app->db->createCommand(
            'SELECT suffix FROM bench_suffixes WHERE vendorid = :v', [':v' => $vid]
        )->queryScalar();
        return $suffix ? ' ' . $suffix : '';
    }

    /** Public wrapper for formatCPU so views can call it without instantiating the service. */
    public static function formatCPUPublic(array $row): string
    {
        return (new self())->formatCPU($row);
    }

    // =========================================================================
    // Chip name resolution (ported from web/yaamp/modules/bench/functions.php)
    // =========================================================================

    private function getChipName(array $row): string
    {
        if ($row['type'] === 'cpu') {
            $device = $this->formatCPU($row);
            $device = str_ireplace(' V2', 'v2', $device);
            $device = str_ireplace(' V3', 'v3', $device);
            $device = str_ireplace(' V4', 'v4', $device);
            $device = str_ireplace(' V5', 'v5', $device);
            if (str_contains($device, 'AMD Athlon ')) {
                return str_replace('AMD ', '', $device);
            }
            $device = preg_replace('/AMD (A6\-[1-9]+[KM]*) APU .+/', '\1', $device);
            $device = preg_replace('/AMD (E[\d]*\-[\d]+) APU .+/', '\1', $device);
            $device = preg_replace('/AMD (A[\d]+\-[\d]+[KP]*) Radeon .+/', '\1', $device);
            $words = explode(' ', $device);
            $chip  = array_pop($words);
            if (str_contains($device, 'Fam.')) $chip = '-';
        } else {
            $device = str_replace(' with Max-Q Design', '', $row['device']);
            $device = str_replace(' COLLECTORS EDITION', '', $device);
            $words  = explode(' ', $device);
            $chip   = array_pop($words);
            if (!is_numeric($chip)) {
                $chip = array_pop($words) . ' ' . $chip;
                $chip = str_replace('GeForce ', '', $chip);
                $chip = str_replace('GT ', '', $chip);
                $chip = str_replace('GTX ', '', $chip);
                $chip = str_replace('650 Ti BOOST', '650 Ti', $chip);
                $chip = str_replace('760 Ti OEM', '760 Ti', $chip);
                $chip = str_replace(' (Pascal)', ' Pascal', $chip);
                $chip = str_replace('Quadro M6000 24GB', 'Quadro M6000', $chip);
                $chip = str_replace('Tesla P100 (PCIe)', 'Tesla P100', $chip);
                $chip = str_replace('Tesla P100-SXM2-16GB', 'Tesla P100', $chip);
                $chip = str_replace('Tesla P100-PCIE-16GB', 'Tesla P100', $chip);
                $chip = str_replace('Tesla V100-SXM2-16GB', 'Tesla V100', $chip);
                $chip = preg_replace('/ASUS ([6-9]\d\dM)/', '\1', $chip);
                $chip = preg_replace('/MSI ([6-9]\d\dM)/', '\1', $chip);
                $chip = preg_replace('/MSI ([6-9]\d\dMX)/', '\1', $chip);
                if (str_contains($chip, 'P106-100') || str_contains($chip, 'CMP3-1')) $chip = 'P106-100';
                if (str_contains($chip, 'P104-100') || str_contains($chip, 'CMP4-1')) $chip = 'P104-100';
            }
            if (str_contains($row['device'], 'Quadro') && !str_contains($chip, 'Quadro')) {
                $chip = "Quadro $chip";
            }
        }

        return $chip ?? '';
    }

    private function formatCPU(array $row): string
    {
        $device = preg_replace('/[ \t]+/', ' ', $row['device']);
        if (str_contains($device, '(R)')) {
            $device = str_replace('(R)', '', $device);
            $device = str_replace(' CPU', '', $device);
            $device = str_replace(' V2', ' v2', $device);
            $device = str_replace(' V3', ' v3', $device);
            $device = str_replace(' V4', ' v4', $device);
        } else {
            $device = str_replace(' Family', '', $device);
            $device = str_replace(' Stepping ', '.', $device);
            $device = str_replace(' GenuineIntel', ' Intel', $device);
            $device = str_replace(' AuthenticAMD', ' AMD', $device);
            $device = str_replace(' Quad-Core', '', $device);
            $device = str_replace(' Dual-Core', '', $device);
            $device = str_replace(' Triple-Core', '', $device);
            $device = str_replace(' Quad Core', '', $device);
            $device = str_replace(' Dual Core', '', $device);
            $device = str_replace(' Triple Core', '', $device);
            $device = str_replace(' Processor', '', $device);
            if (str_contains($device, 'Intel64') && str_contains($device, ' Intel')) {
                $device = str_replace(' Intel', '', $device);
                $device = str_replace('Intel64', 'Intel', $device);
            }
            if (str_contains($device, 'AMD64') && str_contains($device, ' AMD')) {
                $device = str_replace(' AMD', '', $device);
                $device = str_replace('AMD64', 'AMD', $device);
            }
            $device = rtrim($device, ',');
        }
        $device = str_ireplace('(tm)', '', $device);
        $device = str_replace(' APU with Radeon', '', $device);
        $device = str_replace(' APU with AMD Radeon', '', $device);
        $device = str_replace(' version ', ' ', $device);
        $device = str_replace(' Core2 Quad', ' Core2-Quad', $device);
        $device = preg_replace('/(HD|R\d) Graphics/', '', $device);
        $device = preg_replace('/ 0$/', '', $device);
        $device = str_replace(' (1.6GHz Capable)', '', $device);
        if (stristr($device, 'Virtual CPU') || stristr($device, 'QEMU')) {
            $device = 'Virtual';
        }
        return trim($device);
    }
}
