<?php

namespace App\Models;

use App\Support\ProfileReview;
use Illuminate\Database\Eloquent\Model;

/** أثر دائم لكل تغيير على حقل من بيانات المقاول — بيكتبه ContractorFieldHistoryObserver. */
class ContractorFieldHistory extends Model
{
    protected $table = 'contractor_field_history';

    public const UPDATED_AT = null;

    public const SOURCE_LABELS = [
        'app'       => 'التطبيق',
        'dashboard' => 'لوحة التحكم',
        'system'    => 'النظام',
    ];

    protected $fillable = [
        'contractor_id', 'field', 'old_value', 'new_value', 'source',
        'actor_type', 'actor_id', 'actor_name', 'profile_change_id', 'note',
    ];

    public function present(): array
    {
        $isFile = ProfileReview::isFile($this->field) || $this->field === 'logo';

        return [
            'id'           => $this->id,
            'field'        => $this->field,
            'label'        => ProfileReview::label($this->field),
            'old_display'  => ProfileReview::display($this->field, $this->old_value),
            'new_display'  => ProfileReview::display($this->field, $this->new_value),
            'old_file_url' => $isFile ? ProfileReview::fileUrl($this->old_value) : null,
            'new_file_url' => $isFile ? ProfileReview::fileUrl($this->new_value) : null,
            'is_file'      => $isFile,
            'source'       => $this->source,
            'source_label' => self::SOURCE_LABELS[$this->source] ?? $this->source,
            'actor_name'   => $this->actor_name,
            'note'         => $this->note,
            'created_at'   => $this->created_at?->toISOString(),
        ];
    }
}
