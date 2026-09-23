<?php

use App\Models\User;

/*
 * The Sigou panel is inline JavaScript in Blade. One duplicate `const` and
 * the whole panel dies silently in the browser (it once did: two `money`s),
 * so every inline script is put through `node --check`.
 */
it('renders the Sigou panel with scripts that parse', function () {
    $node = trim((string) shell_exec('command -v node'));
    if ($node === '') {
        $this->markTestSkipped('node is not installed');
    }

    $this->actingAs(User::factory()->create());
    $html = view('partials.agent-assistant')->render();
    preg_match_all('#<script(?![^>]*src)[^>]*>(.*?)</script>#s', $html, $m);
    expect($m[1])->not->toBeEmpty();

    foreach ($m[1] as $i => $js) {
        $file = tempnam(sys_get_temp_dir(), 'sigou') . '.js';
        file_put_contents($file, $js);
        exec(escapeshellarg($node) . ' --check ' . escapeshellarg($file) . ' 2>&1', $out, $code);
        @unlink($file);
        expect($code)->toBe(0, "script {$i}: " . implode("\n", $out));
    }
});
