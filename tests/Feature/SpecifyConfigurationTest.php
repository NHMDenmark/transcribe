<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The connection to the collection database (Specify 7) is optional and comes from the configuration, never straight
 * from the environment, so that it still works once the configuration is cached.
 */
class SpecifyConfigurationTest extends TestCase
{
    public function test_nothing_in_the_application_reads_the_environment_directly(): void
    {
        $offenders = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php' && preg_match('/\benv\(/', (string) file_get_contents($file->getPathname()))) {
                $offenders[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        // the proxies middleware mentions env() only in a comment that explains why it does not use it
        $this->assertSame(['app' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Middleware' . DIRECTORY_SEPARATOR . 'TrustProxies.php'], $offenders);
    }

    public function test_the_connection_settings_are_in_the_configuration(): void
    {
        $this->assertSame(['url', 'username', 'password', 'collection'], array_keys(config('services.specify')));
    }

    public function test_without_an_address_the_schema_lookup_is_empty_and_reaches_nothing(): void
    {
        config(['services.specify.url' => '']);

        $this->assertSame([], API_get_schema());
    }

    public function test_the_example_environment_lists_every_setting_of_the_connection(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));

        foreach (['API_URL=', 'API_USERNAME=', 'API_PASSWORD=', 'API_COLLECTION='] as $line) {
            $this->assertStringContainsString($line, $example);
        }
    }
}
