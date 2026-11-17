<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Models\Firm;

/**
 * CRUD API for organisations / firms (Master Settings > Organizations).
 */
class FirmController extends CrudController
{
    protected string $model = Firm::class;

    protected array $rules = [
        'city_id'         => 'required|integer|exists:cities,id',
        'name'            => 'required|string|max:255',
        'short_name'      => 'required|string|max:50',
        'code'            => 'required|string|max:50',
        'registration_no' => 'nullable|string|max:100',
        'address'         => 'nullable|string',
        'email'           => 'nullable|email|max:255',
        'phone_no'        => 'nullable|string|max:50',
        'fax'             => 'nullable|string|max:50',
        'logo'            => 'nullable|string',
    ];

    protected string $orderBy = 'name';

    protected array $searchable = ['name', 'short_name', 'code'];

    protected function beforePersist(Request $request, array &$data): ?Response
    {
        if (!empty($data['logo']) && is_string($data['logo'])) {
            $data['logo'] = $this->processFirmLogo($data['logo']);
        }
        return null;
    }

    /**
     * Decode base64 WebP/image data URL, crop/resize to square, and save to public/uploads/firms.
     */
    private function processFirmLogo(string $logo): string
    {
        if (str_starts_with($logo, '/uploads/') || str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://')) {
            return $logo;
        }

        if (preg_match('/^data:image\/([a-zA-Z0-9\+\-]+);base64,(.+)$/', $logo, $matches)) {
            $rawBinary = base64_decode($matches[2]);
            if ($rawBinary !== false) {
                $dir = dirname(__DIR__, 2) . '/public/uploads/firms';
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                $filename = sprintf('firm_logo_%s.webp', bin2hex(random_bytes(8)));
                $fullPath = $dir . '/' . $filename;

                if (function_exists('imagecreatefromstring') && function_exists('imagewebp')) {
                    $im = @imagecreatefromstring($rawBinary);
                    if ($im !== false) {
                        $w = imagesx($im);
                        $h = imagesy($im);
                        $targetSize = 512;
                        $square = imagecreatetruecolor($targetSize, $targetSize);
                        imagealphablending($square, false);
                        imagesavealpha($square, true);
                        $transparent = imagecolorallocatealpha($square, 255, 255, 255, 127);
                        imagefilledrectangle($square, 0, 0, $targetSize, $targetSize, $transparent);

                        imagecopyresampled($square, $im, 0, 0, 0, 0, $targetSize, $targetSize, $w, $h);
                        imagewebp($square, $fullPath, 90);
                        imagedestroy($im);
                        imagedestroy($square);
                        return '/uploads/firms/' . $filename;
                    }
                }

                file_put_contents($fullPath, $rawBinary);
                return '/uploads/firms/' . $filename;
            }
        }

        return $logo;
    }
}
