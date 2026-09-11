<?php

dataset('login viewports', [
    'mobile 320' => [320, 568],
    'mobile 480' => [480, 800],
    'tablet 768' => [768, 1024],
    'laptop 1024' => [1024, 800],
    'desktop 1440' => [1440, 900],
]);

test('the public login page has no horizontal overflow at supported widths', function (int $width, int $height): void {
    visit('/login')
        ->resize($width, $height)
        ->assertSee('تسجيل الدخول')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth')
        ->assertNoJavaScriptErrors();
})->with('login viewports');
