import { describe, expect, it } from 'vitest';
import ExcelJS from 'exceljs';
import { extractEmails, extractEmailsFromFile } from './recipients';

describe('extractEmails', () => {
  it('keeps each address once, in lower case', () => {
    expect(extractEmails('Jean.Dupont@univ-reims.fr; jean.dupont@univ-reims.fr\nautre@etudiant.fr, pas-une-adresse'))
      .toEqual(['jean.dupont@univ-reims.fr', 'autre@etudiant.fr']);
  });
});

describe('extractEmailsFromFile', () => {
  it('reads a CSV file as text', async () => {
    const file = new File(['nom;mail\nDupont;dupont@univ-reims.fr\n'], 'liste.csv');

    expect(await extractEmailsFromFile(file)).toEqual(['dupont@univ-reims.fr']);
  });

  it('reads every sheet of an xlsx workbook', async () => {
    const workbook = new ExcelJS.Workbook();
    workbook.addWorksheet('S1').addRow(['Dupont', 'dupont@univ-reims.fr']);
    workbook.addWorksheet('S2').addRow(['Martin', 'Martin@Univ-Reims.fr']);
    const file = new File([await workbook.xlsx.writeBuffer()], 'liste.xlsx');

    expect(await extractEmailsFromFile(file)).toEqual(['dupont@univ-reims.fr', 'martin@univ-reims.fr']);
  });
});
