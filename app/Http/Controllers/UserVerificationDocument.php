<?php

namespace App\Http\Controllers;

use App\Models\UserVerificationDocument;
use Illuminate\Http\Request;
use stdClass;
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
                    $docs["address_front"]->name = basename($doc->path);
                } else {
                    $docs["address_back"]->name = basename($doc->path);
                }
            } else if ($doc->document_type == "additional_document") {
                if ($doc->docuemnt_side == "front") {
                    $docs["id_front"]->name = basename($doc->path);
                } else {
                    $docs["id_front"]->name = basename($doc->path);
                }
            }
        }
        return response()->json(["documents" => $docs], 200);
    }

    public function store(Request $request)
    {
        $userId = JWTAuth::parseToken()->authenticate()->id;

        $address_front = $request->file("address_front");
        $address_back = $request->file("address_back");
        $id_front = $request->file("id_front");
        $id_back = $request->file("id_back");

        $dataToUpdate = [];
        if ($address_front) {
            $filename = $address_front->getClientOriginalName();
            $newFileName = uniqid() . '_' . $filename;
            $address_front->storeAs('public/id-proof-images', $newFileName);
            UserVerificationDocument::updateOrCreate([
                "user_id" => $userId,
                "document_type" => "document",
                "docuemnt_side" => "front"
            ], [
                "user_id" => $userId,
                "document_type" => "document",
                "docuemnt_side" => "front",
                "path" => $newFileName,
                "original_filename" => $filename
            ]);
        }
        if ($address_back) {
            $filename = $address_back->getClientOriginalName();
            $newFileName = uniqid() . '_' . $filename;
            $address_back->storeAs('public/id-proof-images', $newFileName);
            UserVerificationDocument::updateOrCreate([
                "user_id" => $userId,
                "document_type" => "document",
                "docuemnt_side" => "back"
            ], [
                "user_id" => $userId,
                "document_type" => "document",
                "docuemnt_side" => "back",
                "path" => $newFileName,
                "original_filename" => $filename
            ]);
        }
        if ($id_front) {
            $filename = $id_front->getClientOriginalName();
            $newFileName = uniqid() . '_' . $filename;
            $id_front->storeAs('public/id-proof-images', $newFileName);
            UserVerificationDocument::updateOrCreate([
                "user_id" => $userId,
                "document_type" => "additional_document",
                "docuemnt_side" => "front"
            ], [
                "user_id" => $userId,
                "document_type" => "additional_document",
                "docuemnt_side" => "front",
                "path" => $newFileName,
                "original_filename" => $filename
            ]);
        }
        if ($id_back) {
            $filename = $id_back->getClientOriginalName();
            $newFileName = uniqid() . '_' . $filename;
            $id_back->storeAs('public/id-proof-images', $newFileName);
            UserVerificationDocument::updateOrCreate([
                "user_id" => $userId,
                "document_type" => "additional_document",
                "docuemnt_side" => "back"
            ], [
                "user_id" => $userId,
                "document_type" => "additional_document",
                "docuemnt_side" => "back",
                "path" => $newFileName,
                "original_filename" => $filename
            ]);
        }

        return response()->json(["data" => $dataToUpdate], 200);
    }
}
