<?php

namespace App\Http\Controllers;

use App\Attachment;
use App\CaseAttachment;
use App\PurchaseAttachment;
use App\TempCaseData;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function attachment(Request $request)
    {
        // https://github.com/kartik-v/bootstrap-fileinput
        // http://plugins.krajee.com/file-input
    }

    private function saveAttachments($request)
    {
        $data = [];
        if ($request->hasFile('attachments')) {
            if (is_array($request->attachments)) {
                $attachments = $request->attachments;
                for ($i = 0, $len = count($attachments); $i < $len; $i++) {
                    array_push($data, Attachment::saveSingleAttachment($attachments[$i]));
                }
            } else {
                return Attachment::saveSingleAttachment($request->attachments);
            }
        }

        return $data;
    }

    public function addAttachment(Request $request)
    {
        $attachments = $this->saveAttachments($request);
        $attachmentsIDs = CaseAttachment::SaveAttachments($attachments, $request->caseID);

        TempCaseData::create([
            'case_id' => $request->caseID,
            'type' => 'ajax',
            'data' => json_encode($attachmentsIDs)
        ]);

        $initialPreview = [];
        $initialPreviewConfig = [];

        for ($i = 0, $len = count($attachments); $i < $len; $i++) {
            $url = env('STORAGE_URL') . $attachments[$i]['server_file_name'];
            $removeUrl = route('remove_attachment');
            $size = (int) $attachments[$i]['size'];
            $caption = $attachments[$i]['original_file_name'];
            $key = $attachments[$i]['id'];
            $type = $attachments[$i]['file_extension'];

            $allowExtensions = ['pdf', 'doc', 'xls', 'ppt', 'mp4', 'jpg', 'png'];

            if (in_array($type, $allowExtensions)) {
                array_push($initialPreviewConfig, [
                    'type' => ($type == 'jpg' || $type == 'png') ? 'image' : $type,
                    'caption'   => $caption, 'size' => $size, 'downloadUrl' => $url, 'url' => $removeUrl, 'key' => $key,
                    'extra' => ['_token' => csrf_token()]
                ]);
//                $urlPreview = $url;
                $urlPreview = '<img src="'.url('images/attach.png').'" class="kv-preview-data file-preview-image">';
            } else {
                array_push($initialPreviewConfig, [
                    'caption'   => $caption, 'size' => $size, 'downloadUrl' => $url, 'url' => $removeUrl, 'key' => $key,
                    'previewAsData' => false, 'extra' => ['_token' => csrf_token()]
                ]);
                $urlPreview = '<img src="'.url('images/attach.png').'" class="kv-preview-data file-preview-image">';
            }

//            if (is_null($initialPreview))
//                $initialPreview = "'" . $urlPreview . "'";
//            else
//                $initialPreview .= ',' . "'" . $urlPreview . "'";

            array_push($initialPreview, $urlPreview);

        }

        return response()->json([
            "overwriteInitial" => false,
            "initialPreview" => $initialPreview,
            "initialPreviewAsData" => true,
            "initialPreviewFileType" => 'image',
            "initialPreviewConfig" => $initialPreviewConfig,
            "append" => true
        ], 200, [], JSON_UNESCAPED_SLASHES);

    }

    public function removeAttachment(Request $request)
    {
        $attachment = Attachment::find($request->key);

        if (Auth::user()->id == $attachment->added_by_user_id) {

            Storage::delete($attachment->server_file_name);

            $attachment->delete();
            CaseAttachment::attachmentID($request->key)->delete();

            return response()->json(['message' => 'Done'], 200);
        } else {
            return response()->json(['Not Authorized. You can only remove your attachments'], 403);
        }

    }


}
