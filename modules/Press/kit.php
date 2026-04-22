<?php

namespace F3CMS;

use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager as Image;

/**
 * kit lib
 */
class kPress extends Kit
{
    /**
     * @param $email
     * @param $verify_code
     */
    public static function fartherData($id)
    {
        \PCMS\kPress::fartherData($id);
    }

    public static function handleCDNImages($article)
    {
        $basePath = $_SERVER['DOCUMENT_ROOT'] ?? getcwd();
        $save_dir = rtrim($basePath, '/') . '/upload/img/lh3/';

        if (!is_dir($save_dir)) {
            mkdir($save_dir, 0755, true);
        }

        $pattern = '/(https:\/\/lh3\.googleusercontent\.com\/[a-zA-Z0-9_-]+)(?:=[a-zA-Z0-9_,-]+)?/i';

        $manager = Image::withDriver(new Driver());

        $errors = [];

        if (preg_match_all($pattern, $article, $matches)) {
            $unique_urls = [];
            foreach ($matches[0] as $idx => $fullUrl) {
                $unique_urls[$fullUrl] = $matches[1][$idx];
            }

            $replacements    = [];
            $downloadJobs    = [];
            $downloadTargets = [];

            foreach ($unique_urls as $original_url => $base_url) {
                $file_id       = md5($base_url);
                $png_filename  = $file_id . '.png';
                $webp_filename = $file_id . '.webp';

                $png_path  = $save_dir . $png_filename;
                $webp_path = $save_dir . $webp_filename;

                if (file_exists($webp_path)) {
                    $replacements[$original_url] = '/upload/img/lh3/' . $webp_filename . '?png';
                    continue;
                }

                $downloadJobs[$file_id] = [
                    'original_url' => $original_url,
                    'base_url'     => $base_url,
                    'png_path'     => $png_path,
                    'webp_path'    => $webp_path,
                    'webp_filename'=> $webp_filename,
                ];

                $downloadTargets[$file_id . ':webp'] = $base_url . '=s0-rw-l100';
                $downloadTargets[$file_id . ':png']  = $base_url . '=s0-rp';
            }

            if (!empty($downloadTargets)) {
                $responses = self::fetchCdnImages($downloadTargets);

                foreach ($downloadJobs as $file_id => $job) {
                    $webpKey = $file_id . ':webp';
                    $pngKey  = $file_id . ':png';

                    $webp_data = $responses[$webpKey] ?? false;
                    $png_data  = $responses[$pngKey] ?? false;
                    $webpReady = false;

                    if (false !== $webp_data) {
                        $webpReady = false !== file_put_contents($job['webp_path'], $webp_data);
                    } elseif (false !== $png_data) {
                        if (false !== file_put_contents($job['png_path'], $png_data)) {
                            try {
                                $image     = $manager->read($job['png_path']);
                                $converted = FSHelper::webp($job['png_path'], $image);
                                $webpReady = file_exists($converted);
                            } catch (\Throwable $th) {
                                $webpReady = false;
                            }
                        }
                    }

                    if ($webpReady) {
                        $replacements[$job['original_url']] = '/upload/img/lh3/' . $job['webp_filename'] . '?png';
                    } else {
                        $errors[] = $job['original_url'];
                    }
                }
            }

            if (!empty($replacements)) {
                $article = strtr($article, $replacements);
            }
        }

        return [
            'content' => $article,
            'errors'  => $errors,
        ];
    }

    protected static function fetchCdnImages(array $targets, int $timeout = 20): array
    {
        if (empty($targets)) {
            return [];
        }

        $multi   = curl_multi_init();
        $handles = [];

        foreach ($targets as $key => $url) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT      => 'kPressBot/1.0',
            ]);

            curl_multi_add_handle($multi, $ch);
            $handles[$key] = $ch;
        }

        $running = null;
        do {
            $mrc = curl_multi_exec($multi, $running);
            if ($mrc === CURLM_CALL_MULTI_PERFORM) {
                continue;
            }
            if ($running) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running && $mrc == CURLM_OK);

        $responses = [];
        foreach ($handles as $key => $ch) {
            $err  = curl_errno($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (0 === $err && $code >= 200 && $code < 300) {
                $responses[$key] = curl_multi_getcontent($ch);
            } else {
                $responses[$key] = false;
            }
            curl_multi_remove_handle($multi, $ch);
        }

        curl_multi_close($multi);

        return $responses;
    }

    public static function rules()
    {
        return [
            'save'        => [
                'lang' => [
                    'required',
                    function ($value) {
                        // false = invalid, string = massage, :attribute :value

                        if (!empty($value['tw']['title'])) {
                            $width = self::strWidth($value['tw']['title']);

                            return ($width > 70) ? '中文標題太長(' . $width . ')' : true;
                        } else {
                            return true;
                        }
                    },
                ],
                // 'meta' => 'required'
            ]
        ];
    }
}

