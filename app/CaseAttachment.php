<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CaseAttachment extends Model
{
    protected $table = 'cases_attachments';
    protected $guarded = [];
    public $timestamps = false;

    public function attachment()
    {
        return $this->belongsTo('App\Attachment', 'attachment_id');
    }

    public function scopeCase($query, $caseID)
    {
        return $query->where('case_id', $caseID);
    }

    public function scopeAttachmentID($query, $attachmentID)
    {
        return $query->where('attachment_id', $attachmentID);
    }

    public static function SaveAttachments($attachments, $caseID)
    {
        $arr = [];
        if (isAssoc($attachments)) {
            $caseAttachment = CaseAttachment::create([
                'attachment_id' => $attachments['id'],
                'case_id'   => $caseID
            ]);

            array_push($arr, $caseAttachment->id);
        } else {
            for ($i = 0, $len = count($attachments); $i < $len; $i++) {
                $caseAttachment = CaseAttachment::create([
                    'attachment_id' => $attachments[$i]['id'],
                    'case_id'   => $caseID
                ]);
                array_push($arr, $caseAttachment->id);
            }
        }

        return $arr;
    }

    public static function AttachmentHistoryUsingCollectionAttachments($attachments)
    {
        $arr = [];

        for ($i = 0, $len = count($attachments); $i < $len; $i++) {
            $arr[$attachments[$i]->attachment->added_by_user_id][] = $attachments[$i];
        }

        return $arr;
    }
}
