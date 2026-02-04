// RFC 7807 Problem Details
export interface ApiError {
  type: string;
  title: string;
  status: number;
  detail: string;
  instance?: string;
  violations?: ValidationViolation[];
}

export interface ValidationViolation {
  propertyPath: string;
  message: string;
}
