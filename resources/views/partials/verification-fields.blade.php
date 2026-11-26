{{--
    Identity and Affiliation Verification: the documents for the chosen account type.
    Used on sign-up and in Account Settings. Vars: $prefix (id prefix), $selectedType,
    $inputClass, $labelClass, $hintClass. A script shows only the fields for the type.
--}}
@php
    use App\Support\AccountVerificationDocuments as Docs;
    $prefix ??= 'v';
    $memberTypes = Docs::MEMBER_TYPES;
    $idChoices = [];
    foreach (\App\Models\User::CLAIMABLE_REQUESTOR_TYPES as $forType) {
        foreach (Docs::identityOptions($forType) as $value => $label) {
            $idChoices[] = [$forType, $value, $label];
        }
    }
    $affiliationChoices = [];
    foreach ($memberTypes as $forType) {
        foreach (Docs::affiliationOptions($forType) as $value => $label) {
            $affiliationChoices[] = [$forType, $value, $label];
        }
    }
@endphp
<div data-verification-fields="{{ $prefix }}" data-selected-type="{{ $selectedType }}" class="space-y-4">
    <p class="text-sm text-slate-600" data-show-for="student">Upload your <strong>PIT Student ID</strong> and your <strong>current Certificate of Registration (COR)</strong>.</p>
    <p class="hidden text-sm text-slate-600" data-show-for="faculty">Upload your <strong>PIT Faculty ID</strong> and <strong>proof of current PIT affiliation</strong>.</p>
    <p class="hidden text-sm text-slate-600" data-show-for="staff">Upload your <strong>PIT Staff ID</strong> and <strong>proof of current PIT affiliation</strong>.</p>
    <p class="hidden text-sm text-slate-600" data-show-for="outsider">Upload a valid <strong>government-issued ID</strong>. No affiliation document is needed.</p>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="{{ $prefix }}_id_type" class="{{ $labelClass }}">ID <span class="text-red-600" aria-hidden="true">*</span></label>
            <select id="{{ $prefix }}_id_type" name="verification_id_type" required class="{{ $inputClass }}" data-for-type-select>
                <option value="">Choose an ID</option>
                @foreach($idChoices as [$forType, $value, $label])
                    <option value="{{ $value }}" data-for="{{ $forType }}" @selected(old('verification_id_type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="{{ $prefix }}_id_file" class="{{ $labelClass }}">ID photo or scan <span class="text-red-600" aria-hidden="true">*</span></label>
            <input id="{{ $prefix }}_id_file" type="file" name="verification_id" required accept=".jpg,.jpeg,.png,.pdf" class="{{ $inputClass }} py-2 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-1 file:text-emerald-700">
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2" data-member-only>
        <div>
            <label for="{{ $prefix }}_affiliation_type" class="{{ $labelClass }}">
                <span data-show-for="student">Certificate of Registration</span><span class="hidden" data-show-for="faculty staff">Proof of current PIT affiliation</span>
                <span class="text-red-600" aria-hidden="true">*</span>
            </label>
            <select id="{{ $prefix }}_affiliation_type" name="verification_affiliation_type" class="{{ $inputClass }}" data-for-type-select>
                <option value="">Choose a document</option>
                @foreach($affiliationChoices as [$forType, $value, $label])
                    <option value="{{ $value }}" data-for="{{ $forType }}" @selected(old('verification_affiliation_type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="{{ $prefix }}_affiliation_file" class="{{ $labelClass }}">Document photo or scan <span class="text-red-600" aria-hidden="true">*</span></label>
            <input id="{{ $prefix }}_affiliation_file" type="file" name="verification_affiliation" accept=".jpg,.jpeg,.png,.pdf" class="{{ $inputClass }} py-2 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-1 file:text-emerald-700">
        </div>
        <div data-show-for="student">
            <label for="{{ $prefix }}_cor_year" class="{{ $labelClass }}">Academic year on the COR <span class="text-red-600" aria-hidden="true">*</span></label>
            <select id="{{ $prefix }}_cor_year" name="cor_academic_year" class="{{ $inputClass }}">
                <option value="">Choose the academic year</option>
                @foreach(Docs::academicYears() as $year)
                    <option value="{{ $year }}" @selected(old('cor_academic_year') === $year)>{{ $year }}</option>
                @endforeach
            </select>
        </div>
        <div data-show-for="student">
            <label for="{{ $prefix }}_cor_semester" class="{{ $labelClass }}">Semester on the COR <span class="text-red-600" aria-hidden="true">*</span></label>
            <select id="{{ $prefix }}_cor_semester" name="cor_semester" class="{{ $inputClass }}">
                <option value="">Choose the semester</option>
                @foreach(Docs::SEMESTERS as $semester)
                    <option value="{{ $semester }}" @selected(old('cor_semester') === $semester)>{{ $semester }}</option>
                @endforeach
            </select>
        </div>
        <div class="hidden" data-show-for="faculty staff">
            <label for="{{ $prefix }}_document_date" class="{{ $labelClass }}">Date issued <span class="text-red-600" aria-hidden="true">*</span></label>
            <input id="{{ $prefix }}_document_date" type="date" name="affiliation_document_date" value="{{ old('affiliation_document_date') }}" max="{{ now()->toDateString() }}" min="{{ now()->subYear()->toDateString() }}" class="{{ $inputClass }}">
            <p class="{{ $hintClass }}">Within the last 12 months, so it shows your current service or employment.</p>
        </div>
    </div>

    <p class="{{ $hintClass }}">JPG, PNG or PDF, up to 5 MB each. Documents must be officially issued or authenticated by the appropriate PIT office. An old PIT ID alone is not accepted as proof of current affiliation.</p>
    <details class="text-xs text-slate-600">
        <summary class="cursor-pointer font-medium text-emerald-700">How your documents are handled</summary>
        <p class="mt-2 leading-relaxed">The Supply Office reviews your identity and affiliation documents together and approves the account once. Until then, outsider rules apply to your requests. You may cover ID numbers. The files are kept privately and deleted after the review; only the result is kept.</p>
    </details>
</div>

@once
    <script>
        // Shows only the documents for the selected account type; hidden fields are disabled.
        window.pitfrVerificationFields = function (root, type) {
            var member = ['student', 'faculty', 'staff'].indexOf(type) !== -1;
            root.querySelectorAll('[data-show-for]').forEach(function (el) {
                var show = el.dataset.showFor.split(' ').indexOf(type) !== -1;
                el.classList.toggle('hidden', !show);
                el.querySelectorAll('input, select').forEach(function (i) { i.disabled = !show; i.required = show; });
            });
            var memberBlock = root.querySelector('[data-member-only]');
            if (memberBlock) {
                memberBlock.classList.toggle('hidden', !member);
                memberBlock.querySelectorAll('input[type=file], select[name=verification_affiliation_type]').forEach(function (i) { i.disabled = !member; i.required = member; });
            }
            root.querySelectorAll('[data-for-type-select]').forEach(function (select) {
                var visible = [];
                Array.prototype.forEach.call(select.options, function (o) {
                    if (!o.value) return;
                    var ok = o.dataset.for === type;
                    o.hidden = !ok; o.disabled = !ok;
                    if (ok) visible.push(o);
                    if (!ok && o.selected) select.value = '';
                });
                if (visible.length === 1) select.value = visible[0].value;
            });
        };
    </script>
@endonce
