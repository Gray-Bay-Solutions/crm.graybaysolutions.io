<?php

namespace Webkul\Admin\Imports;

use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;

class LeadsImport implements ToCollection, WithHeadingRow
{
    protected $leads = [];

    /**
     * @param Collection $rows
     */
    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            // Skip empty rows - check if all required fields are empty
            if ($this->isEmptyRow($row)) {
                continue;
            }
            
            // Validate and clean the data
            $cleanedRow = $this->cleanRowData($row);
            
            // Skip if still no meaningful data after cleaning
            if (empty($cleanedRow['first_name']) && empty($cleanedRow['last_name']) && empty($cleanedRow['company'])) {
                continue;
            }
            
            // Build the lead data structure
            $leadData = $this->buildLeadData($cleanedRow, $index);
            
            if ($leadData) {
                $this->leads[] = $leadData;
            }
        }
    }

    /**
     * Check if a row is empty
     */
    private function isEmptyRow($row): bool
    {
        $requiredFields = ['phone', 'email', 'first_name', 'last_name', 'company'];
        
        foreach ($requiredFields as $field) {
            if (!empty(trim($row[$field] ?? ''))) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Clean and validate row data
     */
    private function cleanRowData($row): array
    {
        return [
            'first_name' => trim($row['first_name'] ?? ''),
            'last_name' => trim($row['last_name'] ?? ''),
            'company' => trim($row['company'] ?? ''),
            'email' => trim($row['email'] ?? ''),
            'phone' => trim($row['phone'] ?? ''),
            'industry_category' => trim($row['industry_category'] ?? ''),
        ];
    }

    /**
     * Build lead data structure
     */
    private function buildLeadData($row, $index): ?array
    {
        try {
            // Build person name
            $personName = trim($row['first_name'] . ' ' . $row['last_name']);
            
            // If no person name, use company name, or create a default
            if (empty($personName) || $personName === ' ') {
                $personName = !empty($row['company']) ? $row['company'] . ' Contact' : 'Unknown Contact ' . ($index + 1);
            }

            // Build lead title
            $leadTitle = '';
            if (!empty($row['company'])) {
                $leadTitle = $row['company'];
            } else {
                $leadTitle = $personName;
            }

            // Build emails array
            $emails = [];
            if (!empty($row['email']) && filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $emails[] = [
                    'value' => $row['email'],
                    'label' => 'work'
                ];
            }

            // Build contact numbers array
            $contactNumbers = [];
            if (!empty($row['phone'])) {
                // Clean phone number (remove non-numeric characters except + and spaces)
                $cleanPhone = preg_replace('/[^\+\d\s\-\(\)]/', '', $row['phone']);
                if (!empty($cleanPhone)) {
                    $contactNumbers[] = [
                        'value' => $cleanPhone,
                        'label' => 'work'
                    ];
                }
            }

            // Build organization data
            $organization = [];
            if (!empty($row['company'])) {
                $organization = [
                    'name' => $row['company'],
                    'entity_type' => 'organizations' // Add entity type for organization
                ];
            }

            // Return structured lead data
            return [
                'title' => $leadTitle,
                'description' => 'Imported lead from bulk upload. ' . 
                               (!empty($row['industry_category']) ? 'Industry: ' . $row['industry_category'] . '. ' : '') .
                               'Please verify and update the details.',
                'lead_value' => 0,
                'entity_type' => 'leads', // Explicitly set entity type
                'person' => [
                    'name' => $personName,
                    'emails' => $emails,
                    'contact_numbers' => $contactNumbers,
                    'organization' => $organization
                ],
                'industry_category' => $row['industry_category']
            ];
        } catch (\Exception $e) {
            // Log error but don't fail the entire import
            \Log::warning('Failed to build lead data for row ' . ($index + 1), [
                'error' => $e->getMessage(),
                'row_data' => $row
            ]);
            return null;
        }
    }

    /**
     * Get the processed leads
     */
    public function getLeads(): array
    {
        return $this->leads;
    }
} 