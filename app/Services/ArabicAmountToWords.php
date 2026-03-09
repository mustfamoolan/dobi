<?php

namespace App\Services;

class ArabicAmountToWords
{
    private $arabicOnes = ["", "واحد", "اثنان", "ثلاثة", "أربعة", "خمسة", "ستة", "سبعة", "ثمانية", "تسعة", "عشرة", "أحد عشر", "اثنا عشر", "ثلاثة عشر", "أربعة عشر", "خمسة عشر", "ستة عشر", "سبعة عشر", "ثمانية عشر", "تسعة عشر"];
    private $arabicTens = ["", "", "عشرون", "ثلاثون", "أربعون", "خمسون", "ستون", "سبعون", "ثمانون", "تسعون"];
    private $arabicHundreds = ["", "مائة", "مائتان", "ثلاثمائة", "أربعمائة", "خمسمائة", "ستمائة", "سبعمائة", "ثمانمائة", "تسعمائة"];
    private $arabicAppellation = ["", "ألف", "مليون", "مليار"];

    public function convert($amount, $currencyCode = "IQD")
    {
        $currencyMap = [
            'IQD' => 'دينار عراقي',
            'USD' => 'دولار أمريكي',
        ];
        $currency = $currencyMap[$currencyCode] ?? $currencyCode;

        if ($amount == 0)
            return "صفر " . $currency;

        $amount = (int) $amount;
        $result = "";
        $groups = [];

        while ($amount > 0) {
            $groups[] = $amount % 1000;
            $amount = (int) ($amount / 1000);
        }

        for ($i = count($groups) - 1; $i >= 0; $i--) {
            if ($groups[$i] == 0)
                continue;

            $groupResult = $this->convertGroup($groups[$i], $i);

            if ($result !== "" && $groupResult !== "") {
                $result .= " و ";
            }
            $result .= $groupResult;
        }

        return "فقط " . $result . " " . $currency . " لا غير";
    }

    private function convertGroup($number, $index)
    {
        $hundreds = (int) ($number / 100);
        $tens = $number % 100;
        $ones = $tens % 10;
        $tensDigit = (int) ($tens / 10);

        $res = "";

        // Hundreds
        if ($hundreds > 0) {
            $res .= $this->arabicHundreds[$hundreds];
        }

        // Tens and Ones
        if ($tens > 0) {
            if ($res !== "")
                $res .= " و ";

            if ($tens < 20) {
                $res .= $this->arabicOnes[$tens];
            } else {
                if ($ones > 0) {
                    $res .= $this->arabicOnes[$ones] . " و ";
                }
                $res .= $this->arabicTens[$tensDigit];
            }
        }

        // Appellation (Thousands, Millions etc)
        if ($index > 0) {
            if ($number == 1) {
                $res = $this->arabicAppellation[$index];
            } elseif ($number == 2) {
                if ($index == 1)
                    $res = "ألفان";
                else
                    $res = $this->arabicAppellation[$index] . "ان";
            } elseif ($number >= 3 && $number <= 10) {
                $res .= " " . $this->arabicAppellation[$index] . " آلاف";
            } else {
                $res .= " " . $this->arabicAppellation[$index];
            }
        }

        // Specialized handles for "ألف" etc.
        if ($index == 1) {
            if ($number == 1)
                return "ألف";
            if ($number == 2)
                return "ألفان";
            if ($number >= 3 && $number <= 10) {
                $tmpRes = $this->arabicHundreds[$hundreds];
                if ($tens > 0) {
                    if ($tmpRes !== "")
                        $tmpRes .= " و ";
                    if ($tens < 20)
                        $tmpRes .= $this->arabicOnes[$tens];
                    else {
                        if ($ones > 0)
                            $tmpRes .= $this->arabicOnes[$ones] . " و ";
                        $tmpRes .= $this->arabicTens[$tensDigit];
                    }
                }
                return $tmpRes . " آلاف";
            }
        }

        return $res;
    }

    /**
     * Helper to use in blade
     */
    public static function translate($amount, $currency = "دينار")
    {
        return (new self())->convert($amount, $currency);
    }
}
