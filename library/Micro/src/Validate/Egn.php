<?php

namespace Micro\Validate;

class Egn extends ValidateAbstract
{
    protected $templates = [
        self::ERROR => 'ЕГН-то е невалидно'
    ];

    /**
     * Validates bulgarian personal identifying numbers
     * algorithm is taken from http://www.grao.bg/esgraon.html
     *
     * @param string $value
     * @return bool
     */
	public function isValid($value)
	{
	    if (is_array($value)) {
	        $value = implode('', $value);
	    }

        if (strlen($value) != 10) {
            $this->messages[] = $this->templates[self::ERROR];
            return false;
        }

        if (!is_numeric($value)) {
            $this->messages[] = $this->templates[self::ERROR];
            return false;
        }

		$year 	= substr($value, 0, 2);
		$month	= substr($value, 2, 2);
		$day	= substr($value, 4, 2);

		if ($month > 0 && $month < 13){
			$year	+= 1900;
		} elseif ($month > 20 && $month < 33){
			$year	+= 1800;
			$month	-= 20;
		} elseif ($month > 40 && $month < 53){
			$year	+= 2000;
			$month	-= 40;
		} else {
			$this->messages[] = $this->templates[self::ERROR];
			return false;
		}


		if (!checkdate($month, $day, $year)) {
			$this->messages[] = $this->templates[self::ERROR];
			return false;
		}

		$checksum = 0;

		$weights = [2, 4, 8, 5, 10, 9, 7, 3, 6];

		for ($i = 0; $i < 9; $i++){
			$checksum += $weights[$i] * $value[$i];
		}

		$checksum %= 11;

		if (($checksum == $value[9]) || ($checksum==10 && $value[9] == 0)) {
			return true;
		}

		$this->messages[] = $this->templates[self::ERROR];
        return false;
	}
}