<?php

declare(strict_types=1);

namespace Mathematicator\Numbers;


use Nette\SmartObject;
use Nette\Utils\Strings;
use Nette\Utils\Validators;

/**
 * This is an implementation of an easy-to-use entity for interpreting numbers.
 *
 * The service supports the storage of the following data types:
 *
 * - Original user input
 * - Integer
 * - Decimal number with adjustable accuracy
 * - Fraction
 *
 * Decimal numbers are automatically converted to a fraction when entered.
 * WARNING: Always use fractions for calculations to avoid problems with rounding of intermediate calculations!
 */
final class SmartNumber
{
	use SmartObject;

	/** @var int */
	private $accuracy;

	/** @var string */
	private $input;

	/** @var string */
	private $string;

	/** @var string */
	private $integer;

	/** @var float */
	private $float;

	/** @var string[] */
	private $fraction = [];


	/**
	 * @param int|null $accuracy
	 * @param string $number number or real user input
	 * @throws NumberException
	 */
	public function __construct(?int $accuracy, string $number)
	{
		$this->accuracy = $accuracy ?? 100;
		$this->setValue($number);
	}


	/**
	 * User real input
	 *
	 * @return string
	 */
	public function getInput(): string
	{
		return $this->input;
	}


	/**
	 * This service represent integer as a string to avoid precision distortion.
	 *
	 * @return string
	 */
	public function getInteger(): string
	{
		return $this->integer;
	}


	/**
	 * @return int
	 */
	public function getAbsoluteInteger(): int
	{
		return \abs($this->integer);
	}


	/**
	 * @return float
	 */
	public function getFloat(): float
	{
		return $this->float;
	}


	/**
	 * Return float number converted to string.
	 *
	 * @return string
	 */
	public function getFloatString(): string
	{
		return (string) $this->float;
	}


	/**
	 * Return number converted to fraction.
	 * For example `2.5` will be converted to `[5, 2]`.
	 * The fraction is always shortened to the basic shape.
	 *
	 * @return string[]
	 */
	public function getFraction(): array
	{
		if (isset($this->fraction[0], $this->fraction[1]) === false) {
			throw new \RuntimeException('Invalid fraction: Fraction must define numerator and denominator.');
		}

		return $this->fraction;
	}


	/**
	 * Detects that the number passed is integer.
	 * Advanced methods through fractional truncation are used for detection.
	 *
	 * @return bool
	 */
	public function isInteger(): bool
	{
		return $this->integer !== null && ($this->input === $this->integer || $this->getFraction()[1] === '1');
	}


	/**
	 * @return bool
	 */
	public function isFloat(): bool
	{
		return $this->isInteger() === false && $this->integer !== null;
	}


	/**
	 * @return bool
	 */
	public function isPositive(): bool
	{
		return $this->float > 0;
	}


	/**
	 * @return bool
	 */
	public function isNegative(): bool
	{
		return $this->float < 0;
	}


	/**
	 * Detects that the number is zero.
	 * For very small decimal number, the function can only return approximate result.
	 *
	 * @return bool
	 */
	public function isZero(): bool
	{
		return $this->float === 0.0 || abs($this->float) < 0.000000001;
	}


	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return $this->getString();
	}


	/**
	 * Returns a number in computer readable form (in LaTeX format).
	 *
	 * @return string
	 */
	public function getString(): string
	{
		if ($this->isInteger() === true) {
			return $this->integer;
		}

		return ($fraction = $this->getFraction()) !== [] && isset($fraction[0], $fraction[1]) === true
			? '\frac{' . $fraction[0] . '}{' . $fraction[1] . '}'
			: $this->getFloatString();
	}


	/**
	 * Returns a number in human readable form (valid search input).
	 *
	 * @return string
	 */
	public function getHumanString(): string
	{
		if ($this->isInteger() === true) {
			return $this->integer;
		}

		return ($fraction = $this->getFraction()) !== [] && isset($fraction[0], $fraction[1]) === true
			? $fraction[0] . '/' . $fraction[1]
			: $this->getFloatString();
	}


	/**
	 * Converts any user input to the internal state of the object.
	 * This method must be called before reading any getter, otherwise the number information will not be available.
	 *
	 * The parsing of numbers takes place in a safe way, in which the values are not distorted due to rounding.
	 * Numbers are handled like a string.
	 *
	 * @internal
	 * @param string $value
	 * @throws NumberException
	 */
	public function setValue(string $value): void
	{
		$value = (string) preg_replace('/(\d)\s+(\d)/', '$1$2', $value);
		$value = rtrim((string) preg_replace('/^(\d*\.\d*?)0+$/', '$1', $value), '.');
		$this->input = $value;

		if (Validators::isNumeric($value)) {
			$toInteger = (string) preg_replace('/\..*$/', '', $value);
			if (Validators::isNumericInt($value)) {
				$this->integer = $toInteger;
				$this->float = (float) $toInteger;
				$this->setStringHelper($toInteger);
				$this->fraction = [$toInteger, '1'];
			} else {
				$this->integer = $toInteger;
				$this->float = (float) $value;
				$this->setStringHelper($value);
				$this->setFractionHelper($value);
			}
		} elseif (preg_match('/^(?<mantissa>-?\d*[.]?\d+)(e|E|^)(?<exponent>-?\d*[.]?\d+)$/', $value, $parseExponential)) {
			$toString = bcmul($parseExponential['mantissa'], bcpow('10', $parseExponential['exponent'], $this->accuracy), $this->accuracy);
			$this->setStringHelper($toString);
			if (Strings::contains($toString, '.')) {
				$floatPow = $parseExponential['mantissa'] * (10 ** $parseExponential['exponent']);
				$this->integer = preg_replace('/\..+$/', '', $toString);
				$this->float = $floatPow;
				$this->setFractionHelper((string) $floatPow);
			} else {
				$this->integer = $toString;
				$this->float = (float) $toString;
				$this->fraction = [$toString, '1'];
			}
		} elseif (preg_match('/^(?<x>-?\d*[.]?\d+)\s*\/\s*(?<y>-?\d*[.]?\d+)$/', $value, $parseFraction)) {
			$short = $this->shortFractionHelper($parseFraction['x'], $parseFraction['y']);
			$this->fraction = [$short[0], $short[1]];
			$this->float = $short[0] / $short[1];
			$this->integer = (string) (int) $this->float;
			$this->setStringHelper(bcdiv((string) $short[0], (string) $short[1], $this->accuracy));
		} elseif (preg_match('/^([+-]{2,})(\d+.*)$/', $value, $parseOperators)) { // "---6"
			$this->setValue((substr_count($parseOperators[1], '-') % 2 === 0 ? '' : '-') . $parseOperators[2]);
		} else {
			NumberException::invalidInput($value);
		}
	}


	/**
	 * @param string $string
	 */
	private function setStringHelper(string $string): void
	{
		$this->string = $string;

		if (preg_match('/^(?<int>.*)(\.|\,)(?<float>.+?)0+$/', $string, $redundantZeros)) {
			$this->string = $redundantZeros['int'] . '.' . $redundantZeros['float'];
		}
	}


	/**
	 * Converts a decimal number to the best available fraction.
	 * The fraction is automatically converted to the basic abbreviated form.
	 *
	 * @param string $float
	 * @param float $tolerance
	 * @return string[] (representation of integers)
	 * @throws NumberException
	 */
	private function setFractionHelper(string $float, float $tolerance = 1.e-8): array
	{
		if (preg_match('/^0+(\.0+)?$/', $float)) {
			return $this->fraction = ['0', '1'];
		}

		if (preg_match('/^0+\.(?<zeros>0{3,})(?<num>\d+?)$/', $float, $floatParser)) {
			return $this->fraction = [$floatParser['num'], '1' . str_repeat('0', \strlen($floatParser['zeros']) + 2)];
		}

		$floatOriginal = $float;
		$float = preg_replace('/^-/', '', $float);

		if ($float >= $tolerance) {
			$numerator = 1;
			$subNumerator = 0;
			$denominator = 0;
			$subDenominator = 1;
			$b = 1 / $float;
			do {
				$b = $b <= $tolerance ? 0 : 1 / $b;
				$a = floor($b);
				$aux = $numerator;
				$numerator = $a * $numerator + $subNumerator;
				$subNumerator = $aux;
				$aux = $denominator;
				$denominator = $a * $denominator + $subDenominator;
				$subDenominator = $aux;
				$b -= $a;
			} while ($denominator > 0 && abs($float - $numerator / $denominator) > $float * $tolerance);
		} elseif (preg_match('/^(.*)\.(.*)$/', $float, $floatParser)) {
			$numerator = ltrim($floatParser[1] . $floatParser[2], '0');
			$denominator = '1' . str_repeat('0', \strlen($floatParser[2]));
		} else {
			$numerator = str_replace('.', '', $float);
			$denominator = '1';
		}

		$short = $this->shortFractionHelper(number_format($numerator, 0, '.', ''), number_format($denominator, 0, '.', ''));

		return $this->fraction = [
			($floatOriginal < 0 ? '-' : '') . $short[0],
			(string) $short[1],
		];
	}


	/**
	 * Automatically converts a fraction to a shortened form.
	 * A prime division is used to shorten the fractions. It is the fastest method for calculation.
	 *
	 * @param string $x
	 * @param string $y
	 * @param int $level
	 * @return string[]
	 * @throws NumberException
	 */
	private function shortFractionHelper(string $x, string $y, int $level = 0): array
	{
		if ($y === '0' || preg_match('/^0+(\.0+)?$/', $y)) {
			NumberException::canNotDivisionFractionByZero($x, $y);
		}

		if (Validators::isNumericInt($x) === false || Validators::isNumericInt($y) === false) {
			return $this->setFractionHelper((string) ($x / $y));
		}

		$originalX = $x;
		$x = number_format(abs((float) $x), 6, '.', '');
		$y = number_format(abs((float) $y), 6, '.', '');

		if ($level > 100) {
			return [$x, $y];
		}

		if ($x % $y === 0) {
			return [(string) (int) ($x / $y), '1'];
		}

		foreach (PrimaryNumber::getList() as $primary) {
			if ($primary > $x || $primary > $y) {
				break;
			}

			if ($x % $primary === 0 && $y % $primary === 0) {
				return $this->shortFractionHelper(
					(string) ($originalX / $primary),
					(string) ($y / $primary),
					$level + 1
				);
			}
		}

		return [($originalX < 0 ? '-' : '') . number_format((float) $x, 0, '.', ''), number_format((float) $y, 0, '.', '')];
	}
}
