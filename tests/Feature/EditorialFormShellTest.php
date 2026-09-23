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
    // Top bar: «Отмена» sits before the publish box.
    $actions = strpos($source, '<PublishActions compact');
    // In the draft publish box «Сохранить черновик» comes before «Опубликовать».
    $save = strpos($source, '{saveButton}');
    $publish = strpos($source, 'label="Опубликовать"');

    expect(substr_count($source, '<LanguageTabs'))->toBe(1)
        ->and($source)->toContain(
            'role="alert"',
            'aria-live="assertive"',
            "router.on('before'",
            "'beforeunload'",
            'useSaveShortcut',
            'Отправить на согласование',
            'Отправить изменения на согласование',
            'Снять с публикации…',
            'ui-splitbtn',
            'Другие действия',
            "? 'bottom' : 'top'",
        )
        ->and($source)->not->toContain('На проверку', 'Отправить на проверку')
        ->and($cancel)->toBeInt()
        ->and($actions)->toBeInt()
        ->and($save)->toBeInt()
        ->and($publish)->toBeInt()
        ->and($cancel)->toBeLessThan($actions)
        ->and($save)->toBeLessThan($publish);
});
