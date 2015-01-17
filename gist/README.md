Herbie Gist Plugin
==================

`Gist` ist ein [Herbie](http://github.com/getherbie/herbie) Plugin, mit dem du Snippets von 
[Gist](https://gist.github.com) in deine Website einbettest.

## Installation

Das Plugin installierst du via Composer.

	$ composer require getherbie/plugin-gist

Danach aktivierst du das Plugin in der Konfigurationsdatei.

    plugins:
        enable:
            - gist

## Anwendung

Mit der folgenden Twig-Funktion kannst du jedes Gist-Snippet einbinden. Beispiel:

    {{ gist id="12345" }}

Wobei "12345" die ID des gewünschten Gist ist.

Für eine bestimmte Datei innerhalb eines Gist kannst Du einen Dateinamen angeben.

    {{ gist id="12345" file="filename.md" }}

## Demo

<http://www.getherbie.org/dokumentation/plugins/gist>
