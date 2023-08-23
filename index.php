<?php

class YoutubeSignatureCipher {
    private function mh(&$a, $b) {
        array_splice($a, 0, $b);
    }

    private function Dj(&$a, $b) {
        $c = $a[0];
        $a[0] = $a[$b % count($a)];
        $a[$b % count($a)] = $c;
    }

    private function du(&$a) {
        $a = array_reverse($a);
    }

    private function ya($a) {
        $a = str_split($a);
        $this->du($a, 56);
        $this->mh($a, 2);
        $this->Dj($a, 18);
        $this->du($a, 20);
        $this->mh($a, 2);
        $this->Dj($a, 45);
        $this->du($a, 10);
        return implode('', $a);
    }

    public function deobfuscate($signatureCipher) {
        $pattern = '/s=([^&]+).*(&url=.+)/';

        if (preg_match($pattern, $signatureCipher, $matches)) {
            $signatureCipher = $matches[1];
            $urlPart = $matches[2];
            
            $url = substr($urlPart, 5);
            $decrypted = $url . '&sig=' . $this->ya($signatureCipher);
    
            return $decrypted;
        }

        return '';
    }
}

class YoutubeDownloader extends YoutubeSignatureCipher {
    private function isValidUrl($url) {
        if (empty($url)) {
            return false;
        }
    
        $parse = parse_url($url);
        $host = $parse['host'];
    
        return in_array($host, array('youtube.com', 'www.youtube.com', 'youtu.be'));
    }

    public function extractVideoData($url) {
        if (!$this->isValidUrl($url)) {
            exit(json_encode(['code' => 400, 'msg' => 'URL inválida']));
        }

        $htmlContent = file_get_contents($url);
        $pattern = '/var ytInitialPlayerResponse = ({.*?});/s';

        if (preg_match($pattern, $htmlContent, $matches)) {
            $jsonString = preg_replace('/\s+/', ' ', $matches[1]);
            $playerResponse = json_decode($jsonString, true);
            
            if (empty($playerResponse)) {
                exit(json_encode(['code' => 400, 'msg' => 'JSON inválido ou arrays não encontrados.']));
            }

            $status = $playerResponse['playabilityStatus'];
            if ($status['status'] != 'OK') {
                $msg = $status['messages'][0] ?? 'Não foi possível baixar esse vídeo.';
                exit(json_encode(['code' => 400, 'msg' => $msg]));
            }

            $data = [
                'videoDetails' => [
                    'title' => $playerResponse['videoDetails']['title'] ?? '',
                    'author' => $playerResponse['videoDetails']['author'] ?? '',
                    'viewCount' => $playerResponse['videoDetails']['viewCount'] ?? '',
                    'thumbnail' => $playerResponse['videoDetails']['thumbnail'] ?? '',
                ],

                'streamingData' => [],
            ];
            
            if (!empty($playerResponse['streamingData']['adaptiveFormats'])) {
                foreach ($playerResponse['streamingData']['adaptiveFormats'] as $media) {

                    if (isset($media['signatureCipher'])) {
                        $signatureCipher = urldecode($media['signatureCipher']);

                        $media['url'] = $this->deobfuscate($signatureCipher);
                    }

                    // Removendo o que eu acho que não é muito necessário
                    unset($media['itag']);
                    unset($media['bitrate']);
                    unset($media['initRange']);
                    unset($media['indexRange']);
                    unset($media['projectionType']);
                    unset($media['averageBitrate']);
                    unset($media['audioChannels']);
                    unset($media['loudnessDb']);
                    unset($media['colorInfo']);
                    unset($media['signatureCipher']);

                    array_push($data['streamingData'], $media);
                }
            }

            exit(json_encode(['code' => 200, 'data' => $data]));
        } else {
            exit(json_encode(['code' => 400, 'msg' => 'Objeto ytInitialPlayerResponse não encontrado.']));
        }
    }
}

$downloader = new YoutubeDownloader();
$downloader->extractVideoData('https://www.youtube.com/watch?v=0nHHZZRYNf4');