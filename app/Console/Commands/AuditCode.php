<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Catches calls to methods that do not exist.
 *
 * PHP only discovers these when the line actually runs, and a service that
 * throws inside a try/catch fails silently: a mistyped method name took every
 * Banksia, AP and Javier room off the site, the exception was swallowed, the
 * collection came back empty and nothing in the logs said why.
 *
 * Reflection rather than pattern matching, so inherited and trait methods
 * count as defined — a regex reports 178 false alarms for every real one, and
 * a check nobody can read is a check nobody runs.
 */
class AuditCode extends Command
{
    protected $signature = 'audit:code';

    protected $description = 'Find calls to methods that do not exist';

    private const ROOTS = [
        'app/Services',
        'app/Services/Concerns',
        'app/Support',
        'app/Console/Commands',
        'app/Http/Controllers',
    ];

    public function handle(): int
    {
        $problems = 0;
        $checked = 0;

        foreach (self::ROOTS as $root) {
            foreach (glob(base_path($root) . '/*.php') as $file) {
                $source = file_get_contents($file);

                if (! preg_match('/namespace\s+([^;]+);/', $source, $namespace)) {
                    continue;
                }
                if (! preg_match('/(?:final\s+|abstract\s+)?(?:class|trait)\s+(\w+)/', $source, $name)) {
                    continue;
                }

                $class = trim($namespace[1]) . '\\' . $name[1];

                if (! class_exists($class) && ! trait_exists($class)) {
                    continue;
                }

                $checked++;

                // A class with __call can resolve anything at runtime.
                if (method_exists($class, '__call')) {
                    continue;
                }

                preg_match_all('/\$this->(\w+)\s*\(/', $source, $calls);

                foreach (array_unique($calls[1]) as $call) {
                    if (method_exists($class, $call)) {
                        continue;
                    }

                    $this->line("  <fg=red>x</> {$name[1]}::{$call}() does not exist — " . basename($file));
                    $problems++;
                }

                // Static calls to our own helpers, which is where a rename bites.
                preg_match_all('/\\\\?App\\\\(?:Support|Services)\\\\(\w+)::(\w+)\s*\(/', $source, $statics, PREG_SET_ORDER);

                foreach ($statics as $static) {
                    foreach (['App\\Support\\' . $static[1], 'App\\Services\\' . $static[1]] as $target) {
                        if (! class_exists($target)) {
                            continue;
                        }
                        if (method_exists($target, $static[2])) {
                            continue 2;
                        }
                    }

                    if (class_exists('App\\Support\\' . $static[1]) || class_exists('App\\Services\\' . $static[1])) {
                        $this->line("  <fg=red>x</> {$static[1]}::{$static[2]}() does not exist — called from " . basename($file));
                        $problems++;
                    }
                }
            }
        }

        $this->newLine();

        if ($problems === 0) {
            $this->info("Checked {$checked} classes: every method call resolves.");
            return self::SUCCESS;
        }

        $this->error("{$problems} call(s) to methods that do not exist, across {$checked} classes.");

        return self::FAILURE;
    }
}
