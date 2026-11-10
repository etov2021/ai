<?php

namespace Micro\Grid\Column;

use Micro\Grid\Column;

class Datetime extends Column
{
    protected $locale;
    protected $dateType;
    protected $timeType;

    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    public function setDateType($format)
    {
        $this->dateType = $format;
    }

    public function setTimeType($format)
    {
        $this->timeType = $format;
    }

    public function render()
    {
        $value = $this->getCurrentValue();

        if (empty($value)) {
            return '';
        }

        try {

            if (!$value instanceof \DateTime) {
                $value = new \DateTime($value);
            }

            if ($this->locale) {
                $fmt = new \IntlDateFormatter(
                    $this->locale,
                    ($this->dateType ? $this->dateType : \IntlDateFormatter::MEDIUM),
                    ($this->timeType ? $this->timeType : \IntlDateFormatter::NONE)
                );
                return $fmt->format($value);
            }

            return $value->format($this->dateType . $this->timeType);

        } catch (\Throwable $e) {
            if (is_debug()) {
                return $e->getMessage();
            }
        }

        return '';
    }
}