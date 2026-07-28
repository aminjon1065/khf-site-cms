<?php

function editorialSource(string $path): string
{
    $source = file_get_contents(resource_path("js/{$path}"));

    expect($source)->toBeString();

    return $source;
}

it('keeps all editorial forms on one shell contract', function (string $type) {
    $source = editorialSource("pages/{$type}/form.tsx");

    expect(substr_count($source, '<EditorialFormShell'))->toBe(1)
        ->and($source)->toContain(
            "from '@/cms/EditorialFormShell'",
            "from '@/routes/{$type}'",
            'backHref={index.url()}',
            'errors={errors}',
            'isDirty={isDirty}',
            'processing={processing}',
            'onSaveDraft=',
            'onSubmitReview=',
            'onPublishNow=',
        )
        ->and($source)->not->toContain(
            '<PageHeader',
            '<LanguageTabs',
            'className="news-form-actions"',
            "form.post(isEdit ? '/",
        );
})->with([
    'news',
    'pages',
    'projects',
    'instructions',
    'announcements',
    'documents',
]);

it('provides one accessible error language action and dirty-state experience', function () {
    $source = editorialSource('cms/EditorialFormShell.tsx');
    $cancel = strpos($source, 'Отмена');
    $save = strpos($source, 'Сохранить черновик');
    $publish = strpos($source, 'Опубликовать');

    expect(substr_count($source, '<LanguageTabs'))->toBe(1)
        ->and($source)->toContain(
            'role="alert"',
            'aria-live="assertive"',
            "router.on('before'",
            "'beforeunload'",
            'useSaveShortcut',
            'Отправить на проверку',
        )
        ->and($cancel)->toBeInt()
        ->and($save)->toBeInt()
        ->and($publish)->toBeInt()
        ->and($cancel)->toBeLessThan($save)
        ->and($save)->toBeLessThan($publish);
});
