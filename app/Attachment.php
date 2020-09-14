<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Attachment extends Model
{
    protected $table = 'attachments';
    protected $guarded = [];
    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo('App\User', 'added_by_user_id');
    }

    public static function saveSingleAttachment($attachment)
    {
        $extension = $attachment->getClientOriginalExtension();
        $originalFilename = $attachment->getClientOriginalName();
        $size = $attachment->getClientSize();
        $serverFileName = time() . '_' . $originalFilename;

        $attachment->storeAs('public/uploads', $serverFileName);

        $attachmentData = Attachment::create([
            'original_file_name'    => $originalFilename,
            'server_file_name'      => $serverFileName,
            'size'                  => $size,
            'file_extension'        => $extension,
            'added_by_user_id'    => isset(Auth::user()->id) ? Auth::user()->id : null
        ]);

        return [
            'id'    => $attachmentData->id,
            'original_file_name'    => $originalFilename,
            'server_file_name'      => $serverFileName,
            'size'                  => $size,
            'file_extension'        => $extension
        ];
    }

}
