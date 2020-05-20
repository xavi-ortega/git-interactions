<?php

namespace App\Helpers;

use Exception;
use Illuminate\Support\Facades\Log;

class GitParserOld
{

    private $index;
    public $text;

    public function __construct(string $text)
    {
        $this->index = 0;
        $this->text = $text;
    }

    public function hasNext()
    {
        return $this->index < mb_strlen($this->text);
    }

    public function expect($type, $token)
    {
        if (!$this->accept($type, $token)) {
            throw new Exception("Expected " . $token . " at " . $this->toString());
        }

        return $this;
    }

    public function skip($type, $token)
    {
        $this->accept($type, $token);
        return $this;
    }

    public function skipUntil($type, $token)
    {
        $this->acceptUntil($type, $token);
        return $this;
    }

    public function skipSpaces()
    {
        return $this->skip('regexp', '/  +/');
    }

    public function acceptUntil($type, $token)
    {
        Log::debug('acceptUntil() ' . $type . " " . $token);
        $result = '';

        while (!$this->peek($type, $token) && $this->hasNext()) {
            $result .= $this->acceptChar();
        }

        return $result;
    }

    public function accept($type, $token)
    {
        Log::debug('accept() ' . $type . " " . $token);
        switch ($type) {
            case 'string':
                return $this->acceptString($token);

            case 'regexp':
                return $this->acceptRegexp($token);

            case 'char':
                return $this->acceptChar();
        }
    }


    public function peek($type, $token)
    {
        switch ($type) {
            case 'string':
                return $this->matchString($token);

            case 'regexp':
                $match = $this->matchRegexp($token);
                return $match ? $match->offset === $this->index : null;

            case 'char':
                return $this->matchChar();
        }
    }

    private function acceptString(string $str)
    {
        Log::debug('acceptString() ' . $str);

        if ($this->matchString($str)) {
            $this->index += mb_strlen($str);
            return $str;
        }

        return null;
    }

    private function matchString(string $str)
    {
        $start = $this->index;
        $end = mb_strlen($str);
        $present = mb_substr($this->text, $start, $end);

        if ($present === $str) {
            return $str;
        }

        return null;
    }

    private function acceptRegExp(string $pattern)
    {
        $match = $this->matchRegExp($pattern);
        if ($match !== null) {
            if ($match->value === "") {
                dump($pattern);
            }
            dump($match, $this->text);
            $this->index = $match->offset;
            return $match->value;
        }

        return null;
    }
    // convertir RegExp en string
    private function matchRegExp(string $pattern)
    {
        $matchingText = mb_substr($this->text, $this->index);
        $match = [];
        preg_match($pattern, $matchingText, $match, PREG_OFFSET_CAPTURE);

        if ($match && count($match) > 0) {
            Log::info('matchRegexp() matched');
            return (object) [
                'value' => $match[0][0],
                'offset' => $this->index + $match[0][1],
                'raw' => $match
            ];
        } else {
            return null;
        }
    }

    private function acceptChar()
    {
        $char = $this->matchChar();
        $this->index++;
        return $char;
    }

    private function matchChar()
    {
        return $this->text[$this->index];
    }

    public function toString()
    {
        $firstHalf = mb_substr($this->text, max(0, $this->index - 30), $this->index);
        // $firstHalf = '';
        // preg_replace('/\n/', $firstHalf, $substr);

        $secondHalf = mb_substr($this->text, $this->index, min($this->index + 30, mb_strlen($this->text)));
        // $secondHalf = '';
        // preg_replace('/\n/', $secondHalf, $substr);

        return '>' . $firstHalf . '·' . $secondHalf . '\nindex' . $this->index;
    }
}
