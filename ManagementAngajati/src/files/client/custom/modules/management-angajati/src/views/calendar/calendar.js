define(['zile-sarbatoare:views/calendar/calendar'], (CalendarZileSarbatoare) => {
    /**
     * Extinde Calendarul furnizat de dependenta ZileSarbatoare, pastrand
     * decorarea zilelor nelucratoare si adaptand exclusiv afisarea concediilor
     * aprobate. O cerere ramane o singura inregistrare, dar este desenata in
     * segmente consecutive care contin numai zile lucratoare.
     */
    return class CalendarManagementAngajati extends CalendarZileSarbatoare {
        setup() {
            super.setup();

            this.iduriUtilizatoriCalendarConcedii = [];

            if (this.options.userId || this.teamIdList || this.isCustomView) {
                return;
            }

            this.wait(
                Espo.Ajax.getRequest('Concedii/utilizatori-calendar')
                    .then(raspuns => {
                        this.iduriUtilizatoriCalendarConcedii = Array.isArray(
                            raspuns.iduriUtilizatori
                        ) ? raspuns.iduriUtilizatori : [];
                    })
                    .catch(() => {
                        this.iduriUtilizatoriCalendarConcedii = [];
                    })
            );
        }

        fetchEvents(deLa, panaLa, callback) {
            const iduri = this.iduriUtilizatoriCalendarConcedii || [];

            if (
                iduri.length === 0 ||
                !this.enabledScopeList.includes('CerereConcediu') ||
                this.options.userId ||
                this.teamIdList ||
                this.isCustomView
            ) {
                super.fetchEvents(deLa, panaLa, callback);
                return;
            }

            let urlPersonal = `Activities?from=${deLa}&to=${panaLa}`;
            urlPersonal += '&scopeList=' + encodeURIComponent(
                this.enabledScopeList.join(',')
            );

            const esteAgenda = this.mode === 'agendaWeek' || this.mode === 'agendaDay';
            urlPersonal += '&agenda=' + encodeURIComponent(esteAgenda);

            let urlConcedii = `Activities?from=${deLa}&to=${panaLa}`;
            urlConcedii += '&userIdList=' + encodeURIComponent(iduri.join(','));
            urlConcedii += '&scopeList=' + encodeURIComponent('CerereConcediu');
            urlConcedii += '&agenda=false';

            if (!this.suppressLoadingAlert) {
                Espo.Ui.notifyWait();
            }

            Promise.all([
                Espo.Ajax.getRequest(urlPersonal),
                Espo.Ajax.getRequest(urlConcedii),
            ]).then(([personale, concedii]) => {
                callback(this.convertToFcEvents([...personale, ...concedii]));
                Espo.Ui.notify(false);
            });

            this.fetching = true;
            this.suppressLoadingAlert = false;

            setTimeout(() => this.fetching = false, 50);
        }

        convertToFcEvents(lista) {
            const elemente = [];

            lista.forEach(inregistrare => {
                const zileCalendar = this.normalizeazaZileCalendar(
                    inregistrare.zileCalendar
                );

                if (
                    inregistrare.scope !== 'CerereConcediu' ||
                    zileCalendar.length === 0
                ) {
                    elemente.push({inregistrare, indexSegment: null});
                    return;
                }

                this.grupeazaZileConsecutive(zileCalendar)
                    .forEach((segment, indexSegment) => {
                        elemente.push({
                            inregistrare: {
                                ...inregistrare,
                                dateStartDate: segment[0],
                                dateEndDate: segment[segment.length - 1],
                            },
                            indexSegment,
                        });
                    });
            });

            const evenimente = super.convertToFcEvents(
                elemente.map(element => element.inregistrare)
            );

            evenimente.forEach((eveniment, index) => {
                const indexSegment = elemente[index].indexSegment;

                if (indexSegment !== null) {
                    eveniment.id = `${eveniment.id}-segment-${indexSegment}`;
                }
            });

            return evenimente;
        }

        normalizeazaZileCalendar(valoare) {
            if (Array.isArray(valoare)) {
                return valoare;
            }

            if (typeof valoare !== 'string' || valoare === '') {
                return [];
            }

            try {
                const rezultat = JSON.parse(valoare);

                return Array.isArray(rezultat) ? rezultat : [];
            } catch (eroare) {
                return [];
            }
        }

        grupeazaZileConsecutive(zile) {
            const zileValide = [...new Set(zile)]
                .filter(zi => /^\d{4}-\d{2}-\d{2}$/.test(zi))
                .sort();
            const segmente = [];

            zileValide.forEach(zi => {
                const segmentCurent = segmente[segmente.length - 1];

                if (!segmentCurent || !this.suntZileConsecutive(
                    segmentCurent[segmentCurent.length - 1],
                    zi
                )) {
                    segmente.push([zi]);
                    return;
                }

                segmentCurent.push(zi);
            });

            return segmente;
        }

        suntZileConsecutive(primaZi, aDouaZi) {
            const primaData = new Date(`${primaZi}T00:00:00Z`);
            const aDouaData = new Date(`${aDouaZi}T00:00:00Z`);

            return aDouaData.getTime() - primaData.getTime() === 86400000;
        }
    };
});
