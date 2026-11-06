@if($ready && $published)
    @if(session('site_form_success_'.$formKey))
        <p role="status" class="site-form__success">Thank you. Your submission has been received.</p>
    @else
        <form action="{{ route('site.forms.store', ['formKey' => $formKey]) }}" method="post" class="site-form" data-site-form="{{ $formKey }}">
            @csrf
            <input type="hidden" name="external_id" value="{{ old('external_id', (string) \Illuminate\Support\Str::uuid()) }}">
            <div aria-hidden="true" class="site-form__honeypot" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">
                <label>Company website<input type="text" name="company_website" tabindex="-1" autocomplete="off"></label>
            </div>

            @foreach($published->fields as $field)
                <div class="site-form__field">
                    <label class="site-form__label" for="{{ 'site-form-'.$formKey.'-'.$field['key'] }}">
                        {{ $field['label'] }}@if($field['required']) <span aria-hidden="true">*</span>@endif
                    </label>
                    @if($field['type'] === 'textarea')
                        <textarea id="{{ 'site-form-'.$formKey.'-'.$field['key'] }}" name="values[{{ $field['key'] }}]" @if($field['required']) required @endif maxlength="5000" aria-invalid="{{ $errors->getBag('site_form_'.$formKey)->get($field['key']) ? 'true' : 'false' }}" class="site-form__input site-form__textarea">{{ old('values.'.$field['key']) }}</textarea>
                    @elseif($field['type'] === 'select')
                        <select id="{{ 'site-form-'.$formKey.'-'.$field['key'] }}" name="values[{{ $field['key'] }}]" @if($field['required']) required @endif aria-invalid="{{ $errors->getBag('site_form_'.$formKey)->get($field['key']) ? 'true' : 'false' }}" class="site-form__input site-form__select">
                            <option value="">Select an option</option>
                            @foreach(($field['options'] ?? []) as $option)
                                <option value="{{ $option['value'] }}" @selected(old('values.'.$field['key']) === $option['value'])>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    @elseif($field['type'] === 'radio' || $field['type'] === 'checkboxes')
                        <fieldset class="site-form__choices" @if($field['required']) aria-required="true" @endif>
                            <legend class="sr-only">{{ $field['label'] }}</legend>
                            @foreach(($field['options'] ?? []) as $option)
                                <label class="site-form__choice">
                                    <input type="{{ $field['type'] === 'radio' ? 'radio' : 'checkbox' }}" name="values[{{ $field['key'] }}]{{ $field['type'] === 'checkboxes' ? '[]' : '' }}" value="{{ $option['value'] }}" @checked($field['type'] === 'radio' ? old('values.'.$field['key']) === $option['value'] : in_array($option['value'], (array) old('values.'.$field['key']), true))>
                                    {{ $option['label'] }}
                                </label>
                            @endforeach
                        </fieldset>
                    @elseif($field['type'] === 'checkbox' || $field['type'] === 'boolean')
                        <label class="site-form__choice">
                            <input type="hidden" name="values[{{ $field['key'] }}]" value="0">
                            <input id="{{ 'site-form-'.$formKey.'-'.$field['key'] }}" name="values[{{ $field['key'] }}]" type="checkbox" value="1" @checked(in_array(old('values.'.$field['key']), ['1', 1, true], true))>
                            {{ $field['label'] }}
                        </label>
                    @else
                        <input id="{{ 'site-form-'.$formKey.'-'.$field['key'] }}" name="values[{{ $field['key'] }}]" type="{{ $field['type'] }}" value="{{ old('values.'.$field['key']) }}" @if($field['required']) required @endif maxlength="5000" aria-invalid="{{ $errors->getBag('site_form_'.$formKey)->get($field['key']) ? 'true' : 'false' }}" class="site-form__input">
                    @endif
                    @foreach($errors->getBag('site_form_'.$formKey)->get($field['key']) as $message)
                        <p class="site-form__error" role="alert">{{ $message }}</p>
                    @endforeach
                </div>
            @endforeach

            <div class="site-form__verification">
                <div data-site-form-turnstile data-sitekey="{{ config('site_forms.turnstile.site_key') }}" data-action="{{ $binding['verification_action'] ?? 'forms' }}"></div>
                @foreach($errors->getBag('site_form_'.$formKey)->get('human_verification') as $message)
                    <p role="alert" class="site-form__error">{{ $message }}</p>
                @endforeach
            </div>
            @foreach($errors->getBag('site_form_'.$formKey)->get('_form') as $message)
                <p role="alert" class="site-form__error">{{ $message }}</p>
            @endforeach
            <button type="submit" class="site-form__submit">{{ $binding['submit_label'] ?? 'Submit' }}</button>
        </form>

        <script>
            window.engageSiteFormsRender = function (scope) {
                if (!window.turnstile) return;
                const root = scope || document;
                root.querySelectorAll('[data-site-form-turnstile]').forEach(function (target) {
                    if (target.dataset.rendered === '1') return;
                    const dialog = target.closest('dialog');
                    if (dialog && !dialog.open) return;
                    try {
                        window.turnstile.render(target, {
                            sitekey: target.dataset.sitekey,
                            action: target.dataset.action,
                        });
                        target.dataset.rendered = '1';
                    } catch (_) { /* Fail closed; no token means no submission. */ }
                });
            };
            window.engageSiteFormsTurnstileLoaded = function () {
                window.engageSiteFormsRender(document);
            };
            document.addEventListener('site-forms:visible', function (event) {
                window.engageSiteFormsRender(event.detail?.container || document);
            });
        </script>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=engageSiteFormsTurnstileLoaded" async defer></script>
    @endif
@else
    <p role="status" class="site-form__unavailable">This form is temporarily unavailable.</p>
@endif