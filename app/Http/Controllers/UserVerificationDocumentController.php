<?php

namespace App\Http\Controllers;

use App\Models\UserVerificationDocument;
use Illuminate\Http\Request;
use stdClass;
use Stripe\Account;
use Stripe\File;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserVerificationDocumentController extends Controller
{
    //

    public function index(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $documents = $user->documents;
        $docs = array(
            "address_front" => new stdClass(),
            "address_back" => new stdClass(),
            "id_front" => new stdClass(),
            "id_back" => new stdClass()
        );

        foreach ($documents as $key => $doc) {
            if ($doc->document_type == "document") {
                if ($doc->docuemnt_side == "front") {
                    $docs["address_front"]->name = basename($doc->original_filename);
                    $docs["address_front"]->is_verified = $doc->is_verified;
                } else {
                    $docs["address_back"]->name = basename($doc->original_filename);
                    $docs["address_back"]->is_verified = $doc->is_verified;
                }
            } else if ($doc->document_type == "additional_document") {
                if ($doc->docuemnt_side == "front") {
                    $docs["id_front"]->name = basename($doc->original_filename);
                    $docs["id_front"]->is_verified = $doc->is_verified;
                } else {
                    $docs["id_back"]->name = basename($doc->original_filename);
                    $docs["id_back"]->is_verified = $doc->is_verified;
                }
            }
        }
        return response()->json(["documents" => $docs], 200);
    }
    private function uploadFile($newFileName)
    {
        $file = File::create(
            [
                'purpose' => 'identity_document',
                'file' => fopen(
                    storage_path('app/public/id-proof-images/' . $newFileName),
                    'r'
                )
            ]
        );
        return $file->id;
    }
    public function store(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $userId = $user->id;

        $address_front = $request->file("address_front");
        $address_back = $request->file("address_back");
        $id_front = $request->file("id_front");
        $id_back = $request->file("id_back");

        $documents = [
            "document" => [],
            "additional_document" => []
        ];

        if ($address_front) {
            $filename = $address_front->getClientOriginalName();
            $newFileName = uniqid() . '_' . $filename;
            $address_front->storeAs('public/id-proof-images', $newFileName);
            $fileId = $this->uploadFile($newFileName);
            UserVerificationDocument::updateOrCreate([
                "user_id" => $userId,
                "document_type" => "document",
                "docuemnt_side" => "front"
            ], [
                "user_id" => $userId,
                "document_type" => "document",
                "docuemnt_side" => "front",
                "path" => $newFileName,
                "original_filename" => $filename,
                "is_verified" => 1,
                "stripe_file_id" => $fileId
            ]);

            $documents["document"]["front"] = $fileId;
        }
        if ($address_back) {
            $filename = $address_back->getClientOriginalName();
            $newFileName = uniqid() . '_' . $filename;
            $address_back->storeAs('public/id-proof-images', $newFileName);
            $fileId = $this->uploadFile($newFileName);
            UserVerificationDocument::updateOrCreate([
                "user_id" => $userId,
                "document_type" => "document",
                "docuemnt_side" => "back"
            ], [
                "user_id" => $userId,
                "document_type" => "document",
                "docuemnt_side" => "back",
                "path" => $newFileName,
                "original_filename" => $filename,
                "is_verified" => 1,
                "stripe_file_id" => $fileId
            ]);

            $documents["document"]["back"] = $fileId;
        }
        if ($id_front) {
            $filename = $id_front->getClientOriginalName();
            $newFileName = uniqid() . '_' . $filename;
            $id_front->storeAs('public/id-proof-images', $newFileName);
            $fileId = $this->uploadFile($newFileName);
            UserVerificationDocument::updateOrCreate([
                "user_id" => $userId,
                "document_type" => "additional_document",
                "docuemnt_side" => "front"
            ], [
                "user_id" => $userId,
                "document_type" => "additional_document",
                "docuemnt_side" => "front",
                "path" => $newFileName,
                "original_filename" => $filename,
                "is_verified" => 1,
                "stripe_file_id" => $fileId
            ]);

            $documents["additional_document"]["front"] = $fileId;
        }
        if ($id_back) {
            $filename = $id_back->getClientOriginalName();
            $newFileName = uniqid() . '_' . $filename;
            $id_back->storeAs('public/id-proof-images', $newFileName);
            $fileId = $this->uploadFile($newFileName);
            UserVerificationDocument::updateOrCreate([
                "user_id" => $userId,
                "document_type" => "additional_document",
                "docuemnt_side" => "back"
            ], [
                "user_id" => $userId,
                "document_type" => "additional_document",
                "docuemnt_side" => "back",
                "path" => $newFileName,
                "original_filename" => $filename,
                "is_verified" => 1,
                "stripe_file_id" => $fileId
            ]);

            $documents["additional_document"]["back"] = $fileId;
        }
        if (count($documents["document"]) > 0 || count($documents["additional_document"]) > 0) {
            Account::update($user->stripe_connected_account_id, [
                "individual" => ["verification" => $documents]
            ]);
        }
        return response()->json(["data" => []], 200);
    }
}
