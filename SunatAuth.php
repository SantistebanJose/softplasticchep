<?php
/**
 * SunatAuth.php
 * Módulo de autenticación OAuth2 contra la API de SUNAT.
 * Obtiene y reutiliza el access_token necesario para llamar a los
 * endpoints de la API (por ejemplo, GRE Emisión de Comprobantes /v1/contribuyente/gem).
 *
 * Requiere: PHP con extensión curl habilitada.
 */

class SunatAuth
{
    private string $clientId;
    private string $clientSecret;
    private string $ruc;
    private string $solUser;     // usuario SOL (sin el RUC)
    private string $solPassword; // clave SOL
    private string $tokenUrl;
    private string $scope;

    // Ruta donde se cachea el token para no pedirlo en cada request
    private string $cacheFile;

    public function __construct(
        string $clientId,
        string $clientSecret,
        string $ruc,
        string $solUser,
        string $solPassword,
        string $scope = 'https://api-cpe.sunat.gob.pe',
        ?string $cacheFile = null
    ) {
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;
        $this->ruc          = $ruc;
        $this->solUser      = $solUser;
        $this->solPassword  = $solPassword;
        $this->scope        = $scope;
        $this->tokenUrl     = "https://api-seguridad.sunat.gob.pe/v1/clientessol/{$clientId}/oauth2/token/";
        $this->cacheFile    = $cacheFile ?? sys_get_temp_dir() . "/sunat_token_{$ruc}.json";
    }

    /**
     * Devuelve un access_token válido, usando caché si aún no expira.
     */
    public function getAccessToken(): string
    {
        $cached = $this->readCache();
        if ($cached !== null) {
            return $cached['access_token'];
        }

        $data = $this->requestNewToken();
        $this->writeCache($data);

        return $data['access_token'];
    }

    /**
     * Llama al endpoint de SUNAT para pedir un token nuevo.
     */
    private function requestNewToken(): array
    {
        // SUNAT exige grant_type=password + usuario y clave SOL,
        // no client_credentials (eso devuelve 204 sin token).
        $postFields = http_build_query([
            'grant_type'    => 'password',
            'scope'         => $this->scope,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'username'      => $this->ruc . $this->solUser, // RUC + usuario SOL, concatenados
            'password'      => $this->solPassword,
        ]);

        $ch = curl_init($this->tokenUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        // Nota: no se llama a curl_close() — no tiene efecto desde PHP 8.0
        // y solo genera un aviso "deprecated" en PHP 8.5+.

        if ($response === false) {
            throw new RuntimeException("Error de conexión con SUNAT: {$curlError}");
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200 || !isset($data['access_token'])) {
            $msg = $data['error_description'] ?? $response;
            throw new RuntimeException("SUNAT rechazó la solicitud de token (HTTP {$httpCode}): {$msg}");
        }

        // expires_in viene en segundos (normalmente 3600)
        $data['expires_at'] = time() + (int) ($data['expires_in'] ?? 3600) - 30; // margen de 30s

        return $data;
    }

    private function readCache(): ?array
    {
        if (!file_exists($this->cacheFile)) {
            return null;
        }

        $data = json_decode(file_get_contents($this->cacheFile), true);

        if (!$data || !isset($data['expires_at']) || $data['expires_at'] <= time()) {
            return null; // expirado o inválido
        }

        return $data;
    }

    private function writeCache(array $data): void
    {
        file_put_contents($this->cacheFile, json_encode($data));
    }
}