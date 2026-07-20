<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every route() name used in a Blade template must resolve to a defined route.
 *
 * An undefined name throws RouteNotFoundException at render time, which means a
 * 500 on any page reaching that line. v2.0.0 shipped three such references
 * (submissions.user_index, workflows.destroy, workflows.steps.destroy) because
 * nothing rendered those branches in the suite. This catches the whole class
 * statically, including links inside conditionals that rarely render.
 */
class BladeRouteReferencesTest extends TestCase
{
    public function test_all_blade_route_names_are_defined(): void
    {
        $defined = collect(Route::getRoutes()->getRoutesByName())->keys()->all();

        // Jetstream scaffolding whose routes only exist when a feature is
        // enabled. These views are likewise only rendered when that feature is
        // on, so the reference is conditional rather than broken. Teams are
        // currently disabled in config/jetstream.php.
        $featureGated = [
            'current-team.update',
        ];

        $problems = [];

        foreach ($this->bladeFiles() as $file) {
            foreach (file($file) as $number => $line) {
                // Match the route() helper but not $request->route('token'),
                // which is a Request method and takes a parameter name.
                preg_match_all('/(?<!->)\broute\(\s*[\'"]([a-zA-Z0-9_.\-]+)[\'"]/', $line, $matches);

                foreach ($matches[1] as $name) {
                    if (! in_array($name, $defined, true) && ! in_array($name, $featureGated, true)) {
                        $relative = str_replace(base_path().'/', '', $file);
                        $problems[] = "{$relative}:".($number + 1)." references undefined route [{$name}]";
                    }
                }
            }
        }

        $this->assertSame([], $problems, "Undefined route names in Blade templates:\n".implode("\n", $problems));
    }

    /**
     * @return array<int, string>
     */
    private function bladeFiles(): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
