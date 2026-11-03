const EMAIL_REGEX = /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g;

/** Formats acceptés par l'import de destinataires ; l'ancien format binaire .xls n'est pas lu. */
export const RECIPIENT_FILE_ACCEPT = '.csv,.xlsx,.tsv,.txt';

export function extractEmails(text: string): string[] {
  return Array.from(new Set((text.match(EMAIL_REGEX) ?? []).map((email) => email.toLowerCase().trim())));
}

/** Adresses trouvées dans un fichier texte ou dans toutes les feuilles d'un classeur .xlsx. */
export async function extractEmailsFromFile(file: File): Promise<string[]> {
  if (!file.name.toLowerCase().endsWith('.xlsx')) {
    return extractEmails(await file.text());
  }

  // Import à la demande : exceljs pèse lourd et ne sert qu'à l'import d'un classeur.
  const { default: ExcelJS } = await import('exceljs');
  const workbook = new ExcelJS.Workbook();
  await workbook.xlsx.load(await file.arrayBuffer());

  const cells: string[] = [];
  workbook.eachSheet((sheet) => {
    sheet.eachRow((row) => {
      row.eachCell((cell) => {
        cells.push(cell.text);
      });
    });
  });

  return extractEmails(cells.join('\n'));
}
