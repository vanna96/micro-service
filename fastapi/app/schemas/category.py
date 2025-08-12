from pydantic import BaseModel, Field, validator
from typing import Optional
import re
import base64
from typing import List

class CategoryBase(BaseModel):
    name: str = Field(..., max_length=100)
    foreign_name: Optional[str] = Field(None, max_length=100)
    parent_id: Optional[int] = None
    status: str = Field(..., description="Status must be either 'active' or 'inactive'")
    image: Optional[str] = None

    @validator('name')
    def validate_name(cls, v):
        if v is not None and not v.strip():
            raise ValueError("Name cannot be empty")
        return v

    @validator('status')
    def validate_status(cls, v):
        if v is not None and v not in ['active', 'inactive']:
            raise ValueError("Status must be either 'active' or 'inactive'")
        return v

    @validator('image')
    def validate_image(cls, v):
        if v:
            if v.startswith("data:"):
                if ";base64," in v:
                    v = v.split(";base64,")[1]
                else:
                    raise ValueError("Invalid base64 image format, missing ';base64,' part.")
            try:
                base64.b64decode(v, validate=True)
            except Exception as e:
                print(f"Error decoding base64: {str(e)}")
                raise ValueError("Invalid base64 image data.")
        return v

class CategoryCreate(CategoryBase):
    pass

class CategoryUpdate(CategoryBase): 
    pass

class Category(BaseModel):
    id: int
    name: str
    foreign_name: Optional[str] = None
    parent_id: Optional[int] = None
    status: Optional[str] = "active"
    image: Optional[str] = None 

    class Config:
        from_attributes = True  # Pydantic will convert SQLAlchemy models to dictionaries here

class CategoryOut(BaseModel):
    id: int
    name: str
    foreign_name: Optional[str]
    status: str
    image: Optional[str]
    parent_id: Optional[int]
    parent_name: Optional[str]
    parent_foreign_name: Optional[str]

    class Config:
        orm_mode = True

class CategoryListOut(BaseModel):
    count: int
    value: List[CategoryOut]

class ResponseWrapper(BaseModel):
    data: CategoryListOut