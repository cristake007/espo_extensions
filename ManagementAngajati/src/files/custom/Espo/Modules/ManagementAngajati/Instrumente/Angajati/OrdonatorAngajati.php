<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Angajati;

use Espo\Entities\User;

/** Ordoneaza consecvent angajatii dupa numele afisat in interfata. */
class OrdonatorAngajati
{
    /**
     * @param iterable<int, User> $angajati
     * @return list<User>
     */
    public function ordoneaza(iterable $angajati): array
    {
        $rezultat = [];

        foreach ($angajati as $angajat) {
            $rezultat[] = $angajat;
        }

        usort($rezultat, fn (User $primul, User $alDoilea): int =>
            $this->compara($primul, $alDoilea));

        return $rezultat;
    }

    private function compara(User $primul, User $alDoilea): int
    {
        $comparatie = strnatcasecmp(
            $this->obtineNumeAfisat($primul),
            $this->obtineNumeAfisat($alDoilea)
        );

        if ($comparatie !== 0) {
            return $comparatie;
        }

        $comparatie = strnatcasecmp(
            (string) $primul->get('userName'),
            (string) $alDoilea->get('userName')
        );

        return $comparatie !== 0 ? $comparatie : strcmp($primul->getId(), $alDoilea->getId());
    }

    private function obtineNumeAfisat(User $angajat): string
    {
        $nume = trim($angajat->getName() ?? '');

        return $nume !== '' ? $nume : (string) $angajat->get('userName');
    }
}
