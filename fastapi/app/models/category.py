from sqlalchemy import Column, Integer, String, ForeignKey, DateTime
from sqlalchemy.orm import relationship
from database import Base

class Category(Base):
    __tablename__ = "categories"

    id = Column(Integer, primary_key=True, index=True)
    name = Column(String(100), nullable=False)
    foreign_name = Column(String(100))
    parent_id = Column(Integer, ForeignKey("categories.id"), nullable=True)
    status = Column(String(20), default="active")
    image = Column(String(255), nullable=True)
    deleted_at = Column(DateTime, nullable=True)
    
    parent = relationship("Category", remote_side=[id], backref="children")
