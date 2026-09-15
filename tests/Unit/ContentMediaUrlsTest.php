<?php

use App\Support\ContentMediaUrls;

test('relativizes storage origins in src and srcset', function () {
    $html = '<img src="http://127.0.0.1:8848/storage/36/a.jpg" '
        .'srcset="https://khf.tj/storage/36/a.jpg 480w, http://127.0.0.1:8090/storage/36/a.jpg 960w">';

    $result = ContentMediaUrls::relativizeHtml($html);

    expect($result)
        ->toBe('<img src="/storage/36/a.jpg" '
            .'srcset="/storage/36/a.jpg 480w, /storage/36/a.jpg 960w">');
});

test('leaves external images and relative urls untouched', function () {
    $html = '<img src="https://img.youtube.com/vi/abc/hq720.jpg"> '
        .'<img src="/storage/5/b.png"> <a href="https://khf.tj/news">ссылка</a>';

    expect(ContentMediaUrls::relativizeHtml($html))->toBe($html);
});

test('skips work entirely when no absolute url is present', function () {
    expect(ContentMediaUrls::relativizeHtml('<p>без ссылок</p>'))
        ->toBe('<p>без ссылок</p>');
});

test('absolutizeHtml prefixes storage paths for external api consumers', function () {
    $html = '<img src="/storage/36/a.jpg" srcset="/storage/36/a.jpg 480w">';

    expect(ContentMediaUrls::absolutizeHtml($html, 'https://cms.khf.tj/'))
        ->toBe('<img src="https://cms.khf.tj/storage/36/a.jpg" '
            .'srcset="https://cms.khf.tj/storage/36/a.jpg 480w">');
});
