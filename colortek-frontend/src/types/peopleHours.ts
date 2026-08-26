export interface PeopleHoursProjectRow {
  project_id: number;
  reference: string;
  name: string;
  hours: number;
  seconds?: number;
}

export interface PeopleHoursDepartmentRow {
  department_id: number;
  code: string;
  name: string;
  hours: number;
  seconds?: number;
}

export interface PeopleHoursEmployeeRow {
  employee_id: number;
  name: string;
  hours: number;
  seconds?: number;
}

export interface PeopleHoursSourceBlock {
  source: string;
  label_en: string;
  label_ar: string;
  by_project: PeopleHoursProjectRow[];
  by_department: PeopleHoursDepartmentRow[];
  by_employee: PeopleHoursEmployeeRow[];
}

export interface PeopleHoursReport {
  from: string;
  to: string;
  workshop: PeopleHoursSourceBlock;
  site: PeopleHoursSourceBlock;
}

export interface PeopleHoursFilters {
  from: string;
  to: string;
  project_id?: number;
  department_id?: number;
  employee_id?: number;
}
