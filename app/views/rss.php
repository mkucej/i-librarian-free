<?php

namespace LibrarianApp;

use DateTime;
use DOMDocument;
use Exception;
use Librarian\Mvc\TextView;

class RssView extends TextView {

    /**
     * @param array $items
     * @return string
     * @throws Exception
     */
    public function main(array $items): string {

        $this->contentType('application/atom+xml');

        $atom = new DOMDocument('1.0', 'utf-8');

        // Feed.
        $feed = $atom->createElement('feed');
        $feed->setAttribute('xmlns', 'http://www.w3.org/2005/Atom');
        $atom->appendChild($feed);

        // Feed title.
        $title = $atom->createElement('title', 'I, Librarian');
        $feed->appendChild($title);

        // Feed ID.
        $id = $atom->createElement('id', IL_BASE_URL . 'index.php/items/rss');
        $feed->appendChild($id);

        // Feed link.
        $link = $atom->createElement('link');
        $link->setAttribute('href', IL_BASE_URL . 'index.php/items/rss');
        $link->setAttribute('rel', 'self');
        $feed->appendChild($link);

        // Website link.
        $link = $atom->createElement('link');
        $link->setAttribute('href', IL_BASE_URL);
        $feed->appendChild($link);

        // Authors.
        $author = $atom->createElement('author');
        $name = $atom->createElement('name', 'I, Librarian');
        $author->appendChild($name);
        $feed->appendChild($author);

        // Feed updated.
        $date = new DateTime($items[0]['added_time']);
        $mtime = $date->format(DATE_ATOM);
        $updated = $atom->createElement('updated', $mtime);
        $feed->appendChild($updated);

        // Entries.
        foreach ($items as $key => $item) {

            $html = <<<HTML
<article>
    <h3>{$item['title']}</h3>
    <p>{$item['abstract']}</p>
</article>
HTML;

            $target = IL_BASE_URL . "index.php/item#summary?id={$item['id']}";

            // Entry.
            $entry = $atom->createElement('entry');
            $feed->appendChild($entry);

            // Entry ID.
            $id = $atom->createElement('id', $target);
            $entry->appendChild($id);

            // Entry link.
            $link = $atom->createElement('link');
            $link->setAttribute('href', $target);
            $entry->appendChild($link);

            // Title.
            $title = $atom->createElement('title', $item['title']);
            $entry->appendChild($title);

            // Content.
            $content = $atom->createElement('content', $html);
            $content->setAttribute('type', 'html');
            $entry->appendChild($content);

            // Updated.
            $date = new DateTime($item['added_time']);
            $mtime = $date->format(DATE_ATOM);
            $updated = $atom->createElement('updated', $mtime);
            $entry->appendChild($updated);
        }

        $this->append($atom->saveXML());

        return $this->send();
    }
}
