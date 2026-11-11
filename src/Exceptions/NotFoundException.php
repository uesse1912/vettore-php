<?php

declare(strict_types=1);

namespace Vettore\Exceptions;

/** 404: risorsa inesistente o appartenente a un altro account (l'API non distingue i due casi). */
final class NotFoundException extends ApiException {}
