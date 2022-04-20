<?php

namespace Actengage\Media\Resources;

use Actengage\Media\Exceptions\UndefinedMethodException;
use Actengage\Media\Exceptions\InvalidResourceException;
use Actengage\Media\Exceptions\UndefinedAttributeException;
use Actengage\Media\Media;
use Actengage\Media\Support\ExifData;
use ColorThief\ColorThief;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Exception\NotReadableException;
use Intervention\Image\ImageManagerStatic;

class Image extends Resource
{
    /**
     * The exif data instance.
     *
     * @var ExifData
     */
    public ExifData $exif;

    /**
     * The image resource.
     *
     * @var \Intervention\Image\Image
     */
    protected \Intervention\Image\Image $image;

    /**
     * Create an instance of the Image resource.
     *
     * @param mixed $data
     * @return void
     */
    public function __construct(mixed $data = null)
    {
        try {
            $this->initialize($data);
        } 
        catch(NotReadableException $e) {
            throw new InvalidResourceException(
                $e->getMessage(), $e->getCode(), $e
            );
        }
    }

    /**
     * Call methods on the image resource and return the value.
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        try {
            return parent::__call($name, $arguments);
        }
        catch(UndefinedAttributeException $e) {
            call_user_func_array([$this->image, $name], $arguments);
        }        

        return $this;
    }

    /**
     * Initialize the resource.
     *
     * @param mixed $data
     * @return void
     */
    public function initialize($data)
    {
        $this->image = ImageManagerStatic::make($data);
        $this->extension = $this->image->extension;
        $this->filename = basename($this->image->basePath());
        $this->filesize = $this->image->filesize();
        $this->mime = $this->image->mime();
        $this->exif = new ExifData($this->image);
    }

    /**
     * Get the model attributes.
     *
     * @return array
     */
    public function attributes(): array
    {
        return parent::attributes([
            'exif' => $this->exif
        ]);
    }

    /**
     * Returns core image resource/obj.
     *
     * @return mixed
     */
    public function core(): mixed
    {
        return $this->image->getCore();
    }

    /**
     * Get the dominant color of the image.
     *
     * @param integer $quality
     * @param array|null $area
     * @param string $outputFormat
     * @param \ColorThief\Image\Adapter\AdapterInterface|string|null $adapter 
     * @return \ColorThief\Color|int|string|null
     */
    public function color(
        int $quality = 10,
        ?array $area = null,
        string $outputFormat = 'obj',
        $adapter = null
    ) {
        return ColorThief::getColor(
            $this->image->getCore(),
            $quality,
            $area,
            $outputFormat,
            $adapter
        );
    }
    
    /**
     * Set the exif data.
     *
     * @param ExifData $exif
     * @return self
     */
    public function exif(ExifData $exif): self
    {
        $this->exif = $exif;

        return $this;
    }

    /**
     * Get the image instance.
     *
     * @return \Intervention\Image\Image
     */
    public function image(): \Intervention\Image\Image
    {
        return $this->image;
    }
    
    /**
     * Get the color palette of the image.
     *
     * @param integer $colorCount
     * @param integer $quality
     * @param array|null $area
     * @param string $outputFormat
     * @param \ColorThief\Image\Adapter\AdapterInterface|string|null $adapter 
     * @return \Illuminate\Support\Collection
     */
    public function palette(
        int $colorCount = 10,
        int $quality = 10,
        ?array $area = null,
        string $outputFormat = 'obj',
        $adapter = null
    ): Collection {
        return collect(ColorThief::getPalette(
            $this->image->getCore(),
            $colorCount,
            $quality,
            $area,
            $outputFormat,
            $adapter
        ));
    }

    /**
     * Store the resource on the disk.
     *
     * @param Media $model
     * @return boolean
     */
    public function store(Media $model): bool
    {
        return Storage::disk($model->disk)->put(
            $model->relative_path, $this->image->encode($this->extension)
        );
    }
}