<?php

declare(strict_types=1);

namespace Jbryn\Downriver\Tests\Overpass;

/**
 * Gives a test its own empty directory and removes it afterwards.
 */
trait TemporaryDirectory
{
    private string $directory;

    protected function createTemporaryDirectory(): void
    {
        $this->directory = sys_get_temp_dir() . '/overpass-test-' . bin2hex(random_bytes(6));
    }

    protected function removeTemporaryDirectory(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }
}
