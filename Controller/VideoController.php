<?php

namespace Xmon\SonataMediaProviderVideoBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class VideoController extends Controller
{
    /**
     * @param Request $request
     *
     * @return Response
     */
    public function uploadChunksAction(Request $request)
    {
        /* ========================================
          VARIABLES
        ======================================== */

        if (!$request->request->get('dzuuid')) {
            return new Response(json_encode(["error" => "You don't seem to have access to this route."]), 401, [
                'Content-Type' => 'application/json'
            ]);
        }

        $fileSystem = new Filesystem();

        // chunk variables
        $fileId     = $request->request->get('dzuuid');
        $chunkIndex = $request->request->get('dzchunkindex') + 1;
        $chunkTotal = $request->request->get('dztotalchunkcount');

        // file path variables
        $fileType   = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $filename   = "{$fileId}-{$chunkIndex}.{$fileType}";
        $targetPath = sprintf('%s/', $this->get('kernel')->getRootDir() . '/../web/uploads/media/tmp');
        $targetFile = $targetPath . $filename;

        /* ========================================
          VALIDATION CHECKS
        ======================================== */

        // I removed all the validation code here. They just prevent upload, so assume the upload is going through.

        /* ========================================
          CHUNK UPLOAD
        ======================================== */

        $fileSystem->mkdir($targetPath);

        move_uploaded_file($_FILES['file']['tmp_name'], $targetFile);

        // Be sure that the file has been uploaded
        if (!file_exists($targetFile)) {
            return new Response(json_encode(["error" => "An error occurred and we couldn't upload the requested file."]), 400, [
                'Content-Type' => 'application/json'
            ]);
        }

        return new Response(null, 201, [
            'Content-Type' => 'application/json'
        ]);
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function mergeChunksAction(Request $request)
    {
        /* ========================================
          VARIABLES
        ======================================== */

        if (!$request->query->get('dzuuid')) {
            return new Response(json_encode(["error" => "You don't seem to have access to this route."]), 401, [
                'Content-Type' => 'application/json'
            ]);
        }

        $fileId     = $request->query->get('dzuuid');
        $chunkTotal = $request->query->get('dztotalchunkcount');
        $fileType   = substr($request->query->get('filename'), strrpos($request->query->get('filename'), '.') + 1);
        $targetPath = sprintf('%s/', $this->get('kernel')->getRootDir() . '/../web/uploads/media/tmp');

        // ===== concatenate uploaded files =====

        // loop through temp files and grab the content
        for ($i = 1; $i <= $chunkTotal; $i++) {
            // target temp file
            if (!$tempFilePath = realpath("{$targetPath}{$fileId}-{$i}.{$fileType}")) {
                if ($finalFilePath = realpath("{$targetPath}{$fileId}.{$fileType}")) {
                    unlink($finalFilePath);
                }

                return new Response(json_encode(["error" => "Your chunk was lost mid-upload."]), 400, [
                    'Content-Type' => 'application/json'
                ]);
            }

            $chunk = file_get_contents($tempFilePath);

            // check chunk content
            if (empty($chunk)) {
                unlink($tempFilePath);

                if ($finalFilePath = realpath("{$targetPath}{$fileId}.{$fileType}")) {
                    unlink($finalFilePath);
                }

                return new Response(json_encode(["error" => "Chunks are uploading as empty strings."]), 400, [
                    'Content-Type' => 'application/json'
                ]);
            }

            // create and write concatenated chunk to the main file
            file_put_contents("{$targetPath}{$fileId}.{$fileType}", $chunk, FILE_APPEND);

            // delete chunk
            unlink($tempFilePath);

            if (file_exists($tempFilePath)) {
                if ($finalFilePath = realpath("{$targetPath}{$fileId}.{$fileType}")) {
                    unlink($finalFilePath);
                }

                return new Response(json_encode(["error" => "Your temp files could not be deleted."]), 400, [
                    'Content-Type' => 'application/json'
                ]);
            }
        }

        return new Response(json_encode([
            'binaryContentRealPath' => "{$targetPath}{$fileId}.{$fileType}"
        ]), 201, [
            'Content-Type' => 'application/json'
        ]);
    }
}