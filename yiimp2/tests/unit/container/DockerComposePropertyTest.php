<?php

namespace yiimp2\tests\unit\container;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Property-Based Tests for Docker Compose Configuration
 * 
 * Feature: yiimp2-dedicated-system
 * 
 * Tests the following properties:
 * - Property 19: Docker Compose service definition
 * - Property 20: Docker Compose network isolation
 * - Property 21: Docker Compose database initialization
 * - Property 22: Docker Compose volume persistence
 * 
 * Validates: Requirements 17.1, 17.2, 17.5, 17.9, 18.2, 18.3, 18.4, 18.6
 */
class DockerComposePropertyTest extends TestCase
{
    private $dockerComposeFile;
    private $dockerComposeConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dockerComposeFile = dirname(dirname(dirname(__DIR__))) . '/../docker-compose.yml';
        
        if (!file_exists($this->dockerComposeFile)) {
            $this->markTestSkipped('docker-compose.yml not found');
        }
        
        try {
            $this->dockerComposeConfig = Yaml::parseFile($this->dockerComposeFile);
        } catch (\Exception $e) {
            $this->markTestSkipped('Failed to parse docker-compose.yml: ' . $e->getMessage());
        }
        
        if (!$this->dockerComposeConfig) {
            $this->markTestSkipped('docker-compose.yml is empty');
        }
    }

    /**
     * Property 19: Docker Compose Service Definition
     * 
     * For any Docker Compose deployment, the docker-compose.yml file should define 
     * all required services (web, database, memcached, stratum, backend) with proper dependencies.
     * 
     * Validates: Requirements 17.1, 17.2, 17.9
     */
    public function testProperty19DockerComposeServiceDefinition()
    {
        $this->assertFileExists(
            $this->dockerComposeFile,
            'docker-compose.yml file must exist'
        );

        $config = $this->dockerComposeConfig;
        $this->assertIsArray($config, 'docker-compose.yml must be valid YAML');
        $this->assertArrayHasKey('services', $config, 'docker-compose.yml must have services section');

        $services = $config['services'];

        // Required services
        $requiredServices = [
            'yiimp2-db' => 'Database service',
            'yiimp2-memcached' => 'Memcached service',
            'yiimp2-web' => 'Web frontend service',
            'yiimp2-stratum-sha256' => 'SHA256 stratum service',
            'yiimp2-backend-main' => 'Main backend service',
            'yiimp2-backend-loop2' => 'Loop2 backend service',
            'yiimp2-backend-blocks' => 'Blocks backend service',
        ];

        foreach ($requiredServices as $serviceName => $description) {
            $this->assertArrayHasKey(
                $serviceName,
                $services,
                "Service '$serviceName' ($description) must be defined"
            );
        }

        // Verify database service configuration
        $dbService = $services['yiimp2-db'];
        $this->assertArrayHasKey('environment', $dbService, 'Database service must have environment variables');
        $this->assertArrayHasKey('MYSQL_DATABASE', $dbService['environment'], 'Database must define MYSQL_DATABASE');
        $this->assertArrayHasKey('MYSQL_USER', $dbService['environment'], 'Database must define MYSQL_USER');
        $this->assertArrayHasKey('MYSQL_PASSWORD', $dbService['environment'], 'Database must define MYSQL_PASSWORD');
        $this->assertArrayHasKey('healthcheck', $dbService, 'Database service must have healthcheck');

        // Verify web service dependencies
        $webService = $services['yiimp2-web'];
        $this->assertArrayHasKey('depends_on', $webService, 'Web service must have dependencies');
        $this->assertArrayHasKey('yiimp2-db', $webService['depends_on'], 'Web service must depend on database');
        $this->assertArrayHasKey('yiimp2-memcached', $webService['depends_on'], 'Web service must depend on memcached');

        // Verify stratum service dependencies
        $stratumService = $services['yiimp2-stratum-sha256'];
        $this->assertArrayHasKey('depends_on', $stratumService, 'Stratum service must have dependencies');
        $this->assertArrayHasKey('yiimp2-db', $stratumService['depends_on'], 'Stratum service must depend on database');

        // Verify backend service dependencies
        $backendService = $services['yiimp2-backend-main'];
        $this->assertArrayHasKey('depends_on', $backendService, 'Backend service must have dependencies');
        $this->assertArrayHasKey('yiimp2-db', $backendService['depends_on'], 'Backend service must depend on database');
    }

    /**
     * Property 20: Docker Compose Network Isolation
     * 
     * For any Docker Compose deployment, all Yiimp2 services should communicate 
     * on a dedicated network isolated from legacy Yiimp.
     * 
     * Validates: Requirements 17.5
     */
    public function testProperty20DockerComposeNetworkIsolation()
    {
        $config = $this->dockerComposeConfig;
        
        // Verify networks section exists
        $this->assertArrayHasKey('networks', $config, 'docker-compose.yml must define networks');
        $this->assertArrayHasKey('yiimp2-network', $config['networks'], 'yiimp2-network must be defined');

        // Verify all services use the yiimp2-network
        $services = $config['services'];
        foreach ($services as $serviceName => $serviceConfig) {
            $this->assertArrayHasKey(
                'networks',
                $serviceConfig,
                "Service '$serviceName' must specify networks"
            );
            $this->assertContains(
                'yiimp2-network',
                $serviceConfig['networks'],
                "Service '$serviceName' must use yiimp2-network"
            );
        }

        // Verify network is bridge type (default isolation)
        $networkConfig = $config['networks']['yiimp2-network'];
        if (isset($networkConfig['driver'])) {
            $this->assertEquals(
                'bridge',
                $networkConfig['driver'],
                'yiimp2-network should use bridge driver for isolation'
            );
        }
    }

    /**
     * Property 21: Docker Compose Database Initialization
     * 
     * For any Docker Compose deployment with database service, the database should be 
     * automatically created and initialized with the schema on first startup.
     * 
     * Validates: Requirements 18.2, 18.3, 18.6
     */
    public function testProperty21DockerComposeDatabaseInitialization()
    {
        $config = $this->dockerComposeConfig;
        $dbService = $config['services']['yiimp2-db'];

        // Verify database initialization volume mount
        $this->assertArrayHasKey('volumes', $dbService, 'Database service must have volumes');
        
        $hasInitScript = false;
        foreach ($dbService['volumes'] as $volume) {
            if (is_string($volume) && strpos($volume, 'yiimp2-init.sql') !== false) {
                $hasInitScript = true;
                // Verify it's mounted to docker-entrypoint-initdb.d
                $this->assertStringContainsString(
                    '/docker-entrypoint-initdb.d/',
                    $volume,
                    'Init script must be mounted to /docker-entrypoint-initdb.d/'
                );
                // Verify it's read-only
                $this->assertStringContainsString(
                    ':ro',
                    $volume,
                    'Init script should be mounted read-only'
                );
            }
        }
        
        $this->assertTrue(
            $hasInitScript,
            'Database service must mount yiimp2-init.sql for automatic initialization'
        );

        // Verify environment variables for database creation
        $env = $dbService['environment'];
        $this->assertArrayHasKey('MYSQL_DATABASE', $env, 'MYSQL_DATABASE must be set for auto-creation');
        $this->assertArrayHasKey('MYSQL_USER', $env, 'MYSQL_USER must be set for auto-creation');
        $this->assertArrayHasKey('MYSQL_PASSWORD', $env, 'MYSQL_PASSWORD must be set for auto-creation');
    }

    /**
     * Property 22: Docker Compose Volume Persistence
     * 
     * For any Docker Compose deployment, database data should persist across 
     * container restarts using named volumes.
     * 
     * Validates: Requirements 18.4
     */
    public function testProperty22DockerComposeVolumePersistence()
    {
        $config = $this->dockerComposeConfig;

        // Verify volumes section exists
        $this->assertArrayHasKey('volumes', $config, 'docker-compose.yml must define volumes');
        $this->assertArrayHasKey(
            'yiimp2-db-data',
            $config['volumes'],
            'yiimp2-db-data volume must be defined for persistence'
        );

        // Verify database service uses the named volume
        $dbService = $config['services']['yiimp2-db'];
        $this->assertArrayHasKey('volumes', $dbService, 'Database service must have volumes');

        $hasDataVolume = false;
        foreach ($dbService['volumes'] as $volume) {
            if (is_string($volume) && strpos($volume, 'yiimp2-db-data:/var/lib/mysql') === 0) {
                $hasDataVolume = true;
            }
        }

        $this->assertTrue(
            $hasDataVolume,
            'Database service must use yiimp2-db-data volume for /var/lib/mysql'
        );
    }

    /**
     * Property: Port Mappings
     * 
     * For any Docker Compose deployment, services should expose correct ports
     * with proper isolation from legacy Yiimp.
     */
    public function testPortMappings()
    {
        $config = $this->dockerComposeConfig;
        $services = $config['services'];

        // Web service should expose port 8090
        $webService = $services['yiimp2-web'];
        $this->assertArrayHasKey('ports', $webService, 'Web service must expose ports');
        $this->assertContains('8090:80', $webService['ports'], 'Web service must map 8090:80');

        // Stratum services should use 4000+ range (offset from legacy 3000+)
        $stratumSha256 = $services['yiimp2-stratum-sha256'];
        $this->assertArrayHasKey('ports', $stratumSha256, 'Stratum SHA256 must expose ports');
        $this->assertContains('4333:3333', $stratumSha256['ports'], 'Stratum SHA256 must map 4333:3333');

        if (isset($services['yiimp2-stratum-sha256-high'])) {
            $stratumSha256High = $services['yiimp2-stratum-sha256-high'];
            $this->assertContains('4334:3333', $stratumSha256High['ports'], 'Stratum SHA256-high must map 4334:3333');
        }

        if (isset($services['yiimp2-stratum-scrypt'])) {
            $stratumScrypt = $services['yiimp2-stratum-scrypt'];
            $this->assertContains('4433:3333', $stratumScrypt['ports'], 'Stratum Scrypt must map 4433:3333');
        }
    }

    /**
     * Property: Service Restart Policies
     * 
     * For any Docker Compose deployment, all services should have appropriate
     * restart policies for production use.
     */
    public function testServiceRestartPolicies()
    {
        $config = $this->dockerComposeConfig;
        $services = $config['services'];

        foreach ($services as $serviceName => $serviceConfig) {
            $this->assertArrayHasKey(
                'restart',
                $serviceConfig,
                "Service '$serviceName' must have restart policy"
            );
            $this->assertEquals(
                'unless-stopped',
                $serviceConfig['restart'],
                "Service '$serviceName' should use 'unless-stopped' restart policy"
            );
        }
    }

    /**
     * Property: Build Context
     * 
     * For any service that builds from Dockerfile, the build context should be
     * properly configured.
     */
    public function testBuildContext()
    {
        $config = $this->dockerComposeConfig;
        $services = $config['services'];

        $buildServices = [
            'yiimp2-web' => 'Dockerfile.yiimp2-web',
            'yiimp2-stratum-sha256' => 'Dockerfile.yiimp2-stratum',
            'yiimp2-backend-main' => 'Dockerfile.yiimp2-backend',
        ];

        foreach ($buildServices as $serviceName => $expectedDockerfile) {
            $serviceConfig = $services[$serviceName];
            $this->assertArrayHasKey(
                'build',
                $serviceConfig,
                "Service '$serviceName' must have build configuration"
            );

            $buildConfig = $serviceConfig['build'];
            $this->assertArrayHasKey(
                'context',
                $buildConfig,
                "Service '$serviceName' must specify build context"
            );
            $this->assertEquals(
                '.',
                $buildConfig['context'],
                "Service '$serviceName' should use root directory as context"
            );

            $this->assertArrayHasKey(
                'dockerfile',
                $buildConfig,
                "Service '$serviceName' must specify dockerfile"
            );
            $this->assertEquals(
                $expectedDockerfile,
                $buildConfig['dockerfile'],
                "Service '$serviceName' should use $expectedDockerfile"
            );
        }
    }
}
