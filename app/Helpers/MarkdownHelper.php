<?php

namespace App\Helpers;

class MarkdownHelper
{
    /**
     * Convert markdown text to HTML
     */
    public static function toHtml(?string $text): string
    {
        if (! $text) {
            return '';
        }

        // First escape the text to prevent XSS
        $text = e($text);

        // Convert **bold** to <strong>bold</strong>
        $text = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $text);

        // Convert *italic* to <em>italic</em>
        $text = preg_replace('/\*(.*?)\*/s', '<em>$1</em>', $text);

        // Process lines and handle bullet points
        $lines = explode("\n", $text);
        $inList = false;
        $result = [];
        $currentText = '';

        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            if (strpos($trimmedLine, '- ') === 0) {
                // This is a bullet point

                // If we have accumulated text, add it with nl2br
                if (! empty($currentText)) {
                    $result[] = nl2br($currentText);
                    $currentText = '';
                }

                // Start a list if not already in one
                if (! $inList) {
                    $result[] = '<ul class="list-disc pl-5 space-y-1 my-2">';
                    $inList = true;
                }

                // Add the list item
                $result[] = '<li class="text-sm">'.substr($trimmedLine, 2).'</li>';
            } else {
                // Not a bullet point

                // Close list if we were in one
                if ($inList) {
                    $result[] = '</ul>';
                    $inList = false;
                }

                // Accumulate regular text
                $currentText .= ($currentText ? "\n" : '').$line;
            }
        }

        // Close any open list
        if ($inList) {
            $result[] = '</ul>';
        }

        // Add any remaining text with nl2br
        if (! empty($currentText)) {
            $result[] = nl2br($currentText);
        }

        // Join all the parts
        return implode('', $result);
    }
}
