<?php

namespace App\Integrations\EngageCore\Contracts;

use App\Integrations\EngageCore\Data\FormSubmissionRequest;
use App\Integrations\EngageCore\Data\FormSubmissionResult;
use App\Integrations\EngageCore\Data\PublishedForm;

interface EngageCoreFormsClient
{
    public function publishedForm(string $formKey): PublishedForm;

    public function submit(FormSubmissionRequest $submission): FormSubmissionResult;
}