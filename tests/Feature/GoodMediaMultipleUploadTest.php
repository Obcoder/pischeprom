<?php

namespace Tests\Feature;

use Tests\TestCase;

class GoodMediaMultipleUploadTest extends TestCase
{
    public function test_good_media_dialog_uploads_all_selected_files_and_keeps_failures_for_retry(): void
    {
        $component = (string) file_get_contents(
            resource_path('js/Components/Goods/GoodMediaTab.vue')
        );

        $this->assertStringContainsString('v-model="uploadForm.files"', $component);
        $this->assertStringContainsString('multiple', $component);
        $this->assertStringContainsString('for (const file of files)', $component);
        $this->assertStringContainsString('uploadProgress.completed += 1', $component);
        $this->assertStringContainsString(
            'uploadForm.files = uploadFailures.value.map((failure) => failure.file)',
            $component
        );
        $this->assertStringNotContainsString('const file = extractFile(uploadForm.file)', $component);
    }
}
