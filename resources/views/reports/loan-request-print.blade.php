<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Loan Application Form</title>
        @include('reports.partials.loan-request-styles')
    </head>
    <body>
        @include('reports.partials.loan-request-document')
        <script>
            (() => {
                let printed = false;

                const triggerPrint = () => {
                    if (printed) {
                        return;
                    }

                    printed = true;
                    window.print();
                };

                const waitForFonts = () => {
                    if (document.fonts && document.fonts.ready) {
                        document.fonts.ready
                            .then(() => {
                                setTimeout(triggerPrint, 100);
                            })
                            .catch(triggerPrint);
                        return;
                    }

                    setTimeout(triggerPrint, 100);
                };

                window.addEventListener('load', waitForFonts);
            })();
        </script>
    </body>
</html>
