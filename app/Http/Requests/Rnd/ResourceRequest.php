<?php

namespace App\Http\Requests\Rnd;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates resource creation and update.
 * Shared by both Api\Rnd\ResourceController and Api\Admin\ResourceController —
 * both roles are permitted in authorize() since both can upload resources.
 *
 * Exactly one of file_path / url is expected depending on type:
 *  - type=pdf typically pairs with file_path (uploaded file)
 *  - type=video/link typically pairs with url (external link)
 *  - type=article may use either or neither (body content lives elsewhere
 *    in a future rich-text field if added later)
 * This is enforced loosely (nullable on both) since the schema itself
 * does not constrain it — file upload handling is out of scope for this
 * delivery (see file-uploads in the priority list, item #7).
 */
class ResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isRnd() || $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'title'       => ['required_without:id', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type'        => ['required_without:id', 'in:article,pdf,video,link'],
            'file_path'   => ['nullable', 'string', 'max:500'],
            'url'         => ['nullable', 'url', 'max:1000'],
            'is_active'   => ['nullable', 'boolean'],
        ];
    }
}
