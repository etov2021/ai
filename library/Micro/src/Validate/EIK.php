<?php

namespace Micro\Validate;

use Micro\Validate\ValidateAbstract;

class EIK extends ValidateAbstract
{
	const EIK9_LENGTH = 9;
	const EIK13_LENGTH = 13;
	const EIK13_PART_LENGTH = 5;

	const MODUL = 11;
	const MODUL_MAGIC = 10;

	const NOT_VALID = 'bulstatNotValid';

	protected $templates = [
	    self::NOT_VALID => 'Bulstat is not valid'
	];

	public function isValid($eik)
	{
		$ret = false;

		if (ctype_digit((string) $eik)) {
			$len = strlen($eik);
			if ($len == self::EIK9_LENGTH || $len == self::EIK13_LENGTH) {
				if ($len == self::EIK9_LENGTH) {
					$ret = self::validate9($eik);
				} else {
					if (self::validate9(substr($eik, 0, 9))) {
						$ret = self::validate13(substr($eik, -1 * self::EIK13_PART_LENGTH));
					}
				}
			}
		}

		if ($ret === false) {
            $this->messages[] = $this->templates[self::NOT_VALID];
		}

		return $ret;
	}

	private static function validate9($eik)
	{
		$v_9 = array(
		    array(1,2,3,4,5,6,7,8),
		    array(3,4,5,6,7,8,9,10)
		);

		return self::validate_n($eik, self::EIK9_LENGTH, $v_9);
	}

	private static function validate13($eik)
	{
		$v_13 = array(
		    array(2,7,3,5),
		    array(4,9,5,7)
        );

		return self::validate_n($eik, self::EIK13_PART_LENGTH, $v_13);
	}

	private static function validate_n($eik, $n, $coef_arr)
	{
		$ret = false;

		$eik_arr = str_split($eik);

		$pass_1_sum = 0;

		for($i = 0; $i < $n - 1; $i++) {
			$pass_1_sum += $coef_arr[0][$i] * $eik_arr[$i];
		}

		$rest1 = $pass_1_sum % self::MODUL;

		if ($rest1 == 10) {

			$pass_2_sum = 0;

			for($i = 0; $i < $n - 1; $i++) {
				$pass_2_sum += $coef_arr[1][$i] * $eik_arr[$i];
			}

			$rest2 = $pass_2_sum % 11;

			if ($rest2 == self::MODUL_MAGIC) {
				$rest2 = 0;
			}

			if ($rest2 == $eik_arr[$n - 1]) {
				$ret = true;
			}

		} else {
			if ($rest1 == $eik_arr[$n - 1]) {
				$ret = true;
			}
		}

		return $ret;
	}
}