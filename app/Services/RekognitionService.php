<?php

namespace App\Services;

use Aws\Rekognition\RekognitionClient;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Aws\Laravel\AwsFacade as AWS; // <-- Importamos el Facade del paquete

class RekognitionService
{
    /**
     * @var RekognitionClient
     */
    protected $client;

    /**
     * @var string
     */
    protected $collectionId;

    public function __construct()
    {
        /**
         * El Service Provider de 'aws/aws-sdk-php-laravel' nos permite
         * crear el cliente de forma limpia usando un Facade.
         * Automáticamente leerá las credenciales desde tu archivo .env
         * (AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION).
         */
        $this->client = AWS::createClient('rekognition');

        // Obtenemos el ID de la colección desde el archivo de configuración de servicios.
        $this->collectionId = config('aws.rekognition.collection_id');
    }

    /**
     * Indexa un rostro en la colección de Rekognition.
     *
     * @param string $imageBytes Los bytes de la imagen.
     * @param string $externalImageId Un ID externo, como el número de empleado.
     * @return string|null El FaceId generado por AWS o null si falla.
     */
    public function indexFace(string $imageBytes, string $externalImageId): ?string
    {
        try {
            $result = $this->client->indexFaces([
                'CollectionId'        => $this->collectionId,
                'DetectionAttributes' => ['DEFAULT'],
                'ExternalImageId'     => $externalImageId,
                'Image'               => ['Bytes' => $imageBytes],
                'MaxFaces'            => 1, // Nos aseguramos de indexar solo una cara.
                'QualityFilter'       => 'AUTO',
            ]);

            if (!empty($result['FaceRecords'])) {
                return $result['FaceRecords'][0]['Face']['FaceId'];
            }

            Log::warning('Rekognition: No se detectaron rostros en la imagen para indexar.', ['externalId' => $externalImageId]);
            return null;

        } catch (AwsException $e) {
            Log::error('AWS Rekognition Error (indexFaces): ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return null;
        }
    }

    /**
     * Busca un rostro en la colección a partir de una imagen.
     *
     * @param string $imageBytes Los bytes de la imagen capturada.
     * @return string|null El FaceId de la coincidencia más cercana o null si no se encuentra.
     */
    public function searchFaceByImage(string $imageBytes): ?string
    {
        try {
            $result = $this->client->searchFacesByImage([
                'CollectionId'       => $this->collectionId,
                'FaceMatchThreshold' => 95, // Umbral de similitud (ajusta según necesites).
                'Image'              => ['Bytes' => $imageBytes],
                'MaxFaces'           => 1,
            ]);

            if (!empty($result['FaceMatches'])) {
                return $result['FaceMatches'][0]['Face']['FaceId'];
            }
            
            return null;

        } catch (AwsException $e) {
            Log::error('AWS Rekognition Error (searchFacesByImage): ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return null;
        }
    }

    /**
     * Elimina un rostro de la colección usando su FaceId.
     *
     * @param string $faceId El FaceId a eliminar.
     * @return bool True si se eliminó con éxito.
     */
    public function deleteFace(string $faceId): bool
    {
        try {
            $this->client->deleteFaces([
                'CollectionId' => $this->collectionId,
                'FaceIds' => [$faceId],
            ]);
            return true;
        } catch (AwsException $e) {
            Log::error('AWS Rekognition Error (deleteFaces): ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return false;
        }
    }
}
